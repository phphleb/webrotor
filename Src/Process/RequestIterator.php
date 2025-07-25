<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Process;

use Iterator;
use Phphleb\Webrotor\Src\Handler\Psr7Converter;
use Phphleb\Webrotor\Src\InternalConfig;
use Phphleb\Webrotor\Src\Process\Spawn\TemporaryWorkerCreatorInterface;
use Phphleb\Webrotor\Src\Session\SessionManagerInterface;
use Phphleb\Webrotor\Src\Storage\StorageInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * @internal
 *
 * @implements Iterator<string, ServerRequestInterface>
 */
final class RequestIterator implements Iterator
{
    public const MAX_REQUESTS_TO_START_WORKER = 3;

    /**
     * @var string
     */
    private $tag = '';

    /**
     * @var ServerRequestInterface
     */
    private $request = null;

    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var InternalConfig
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Psr7Converter
     */
    private $converter;

    /**
     * @var TemporaryWorkerCreatorInterface
     */
    private $workerCreator;

    /**
     * @var int
     */
    private $temporaryWorkerCount = 0;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var int
     */
    private $idleTimeout;

    /**
     * @var int
     */
    private $idleMaxTimeout;

    public function __construct(
        StorageInterface                $storage,
        InternalConfig                  $config,
        Psr7Converter                   $converter,
        LoggerInterface                 $logger,
        TemporaryWorkerCreatorInterface $workerCreator,
        SessionManagerInterface         $sessionManager
    )
    {
        $this->storage = $storage;
        $this->config = $config;
        $this->logger = $logger;
        $this->converter = $converter;
        $this->workerCreator = $workerCreator;
        $this->sessionManager = $sessionManager;
        $this->idleTimeout = $this->config->getIdleTimeoutSec();
        $this->idleMaxTimeout = $this->idleTimeout + (int)$this->config->getStartUnixTime();
    }

    /**
     * @inheritDoc
     */
    public function current(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        $this->tag = '';
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return $this->tag;
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return $this->search();
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->tag = '';
    }

    /**
     * Search raw requests data.
     */
    private function search(): bool
    {
        $id = $this->config->getCurrentWorkerId();
        $start = $this->config->getStartUnixTime();
        $max = $this->config->getWorkerLifetimeSec();
        $tempMax = $this->config->getTemporaryWorkerLifetimeSec() ?: $max;
        $delay = $this->config->getWorkerRequestDelayMicroSec();

        while (true) {
            $requestKeys = $this->storage->keys(Worker::REQUEST_TYPE);
            $responseKeys = $this->storage->keys(Worker::RESPONSE_TYPE);

            $time = microtime(true);

            $this->temporaryWorkerCount or $this->createNewWorkerAsNeeded(count($requestKeys));

            // If the lifetime for a worker or temporary worker has expired, the search ends.
            if ($time >= $start + ($this->config->isTemporaryWorker() ? $tempMax : $max)) {
                $this->logger->debug('(W) Worker #{id} lifetime has expired and the process is terminated.', ['id' => $id]);
                return false;
            }

            if ($this->idleTimeout && $time >= $this->idleMaxTimeout) {
                $this->logger->debug('(W) Worker #{id} idle timeout exceeded, the process is terminated.', ['id' => $id]);
                return false;
            }

            [$requestKeys, $responseKeys] = WorkerHelper::sortRawKeys($requestKeys, $responseKeys, $id);

            $unprocessed = array_diff($requestKeys, $responseKeys);

            if (!$unprocessed) {
                $delay and usleep($delay);
                continue;
            }
            $time = microtime(true);
            foreach ($unprocessed as $key => $item) {
                if (WorkerHelper::checkIsOlder($item, Worker::REQUEST_TYPE, $time, $this->config->getCodeVersion())) {
                    unset($unprocessed[$key]);
                }
            }
            if (!$unprocessed) {
                $delay and usleep($delay);
                continue;
            }

            // Processing starts with the oldest requests.
            sort($unprocessed);
            $tag = current($unprocessed);
            $this->tag = $tag;

            $this->logger->debug("Request {tag} for worker detected.", ['tag' => $tag]);

            $data = $this->storage->get($tag, Worker::REQUEST_TYPE);
            $this->logger->debug('The {tag} tag has been received for processing and is being removed from the storage.', ['tag' => $tag]);
            if (!$this->storage->delete($tag, Worker::REQUEST_TYPE)) {
                // Was taken away by a competing process.
                continue;
            }
            $responseTag = $responseKeys[$tag] ?? null;
            if ($responseTag && !$this->config->isDebug()) {
                if (!$this->storage->delete($responseTag, Worker::RESPONSE_TYPE)) {
                    // Was taken away by a competing process.
                    continue;
                }
            }
            if (!$data) {
                $this->logger->warning('Failed to receive request data by worker for ' . $tag);
                continue;
            }
            $array = [];
            try {
                /**
                 * @var null|array{
                 *      serverParams: array<string, mixed>,
                 *      headers: string[][],
                 *      body: string,
                 *      version: string,
                 *      uri: string,
                 *      method: string,
                 *      attributes: array{
                 *          session: array<string, mixed>,
                 *          sessionId: string,
                 *          sessionName: string,
                 *          cookie: array<string, mixed>,
                 *          get: array<string, mixed>,
                 *          post: array<string, mixed>,
                 *          env: array<string, mixed>,
                 *          files: array<string, mixed>
                 *      }
                 *  } $array
                 */
                $array = json_decode((string)$data, true);
                if ($array) {
                    $this->idleMaxTimeout += $this->idleTimeout;
                    $this->sessionManager->clean();
                    $this->request = $this->converter->convertArrayToServerRequest($array);
                }
            } catch (Throwable $t) {
                $this->logger->error("(A) Failed to convert data to response object for {tag}. " . $t, ['tag' => $tag]);
            }
            if (!$array) {
                $this->logger->warning('(W) The found request {tag} could not be converted.', ['tag' => $tag]);
                continue;
            }
            $this->logger->debug("Request {tag} received successfully.", ['tag' => $tag]);

            return true;
        }
    }

    /**
     * Creates a new temporary worker if there are a lot of unprocessed requests.
     */
    private function createNewWorkerAsNeeded(int $requestCount): void
    {
        $interpreter = $this->config->getInterpreterPathPattern();

        if ($interpreter && $requestCount > self::MAX_REQUESTS_TO_START_WORKER) {
            $this->workerCreator->createWorker();
            $this->temporaryWorkerCount++;
        }
    }
}
