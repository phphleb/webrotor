<?php

declare(strict_types=1);

namespace Phphleb\WebRotor\Src\Process;

use Phphleb\WebRotor\Src\Handler\Psr7Converter;
use Phphleb\WebRotor\Src\InternalConfig;
use Phphleb\WebRotor\Src\Log\LoggerManager;
use Phphleb\WebRotor\Src\Session\SessionManagerInterface;
use Phphleb\WebRotor\Src\Storage\StorageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * @internal
 */
class Worker
{
    public const REQUEST_TYPE = 'request';

    public const RESPONSE_TYPE = 'response';

    public const WORKER_TYPE = 'worker';

    /**
     * @var InternalConfig
     */
    private $config;

    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var int|null
     */
    private $workerActiveId = null;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Psr7Converter
     */
    private $converter;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    public function __construct(
        InternalConfig          $config,
        StorageInterface        $storage,
        Psr7Converter           $converter,
        LoggerInterface         $logger,
        SessionManagerInterface $sessionManager
    )
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->logger = $logger;
        $this->converter = $converter;
        $this->sessionManager = $sessionManager;
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    public function getErrorResponse(string $e): ResponseInterface
    {
        return $this->converter->convertThrowableToResponse($e);
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getSessionManager(): SessionManagerInterface
    {
        return $this->sessionManager;
    }

    /**
     * Saves the current request to storage.
     * Not used in worker mode.
     *
     * @return array<int, mixed>
     */
    public function setCurrentRequest(): array
    {
        $id = $this->workerActiveId;
        if ($id === null) {
            $id = $this->getDistributionId();
        }

        $tag = $this->createTag($id);

        try {
            $data = $this->converter->convertCurrentServerRequestToArray();
            // Due to the protected use of temporary files, the request will be processed without using a worker.
            if ($data['attributes']['files']) {
                $this->logger->info('(S) For an request {tag} with uploaded files, the standard handler will be used.', ['tag' => $tag]);

                return [$tag, []];
            }
            $this->storage->set($tag, self::REQUEST_TYPE, (string)json_encode($data));
        } catch (Throwable $t) {
            $this->logger->error('(S) Failed to convert received array to request object for {tag}.', ['tag' => $tag]);
        }
        return [$tag, $data ?? []];
    }

    /**
     * Creates a suitable tag to store requests and responses.
     */
    public function createTag(int $workerId): string
    {
        $executionTime = $this->config->getMaxExecutionTime();
        $max = 1000000;
        while (true) {
            $id = uniqid('', false);
            $tag = $workerId . '-' . ((int)(microtime(true) * 1000000)) . '-' . $executionTime . '-' . $id;
            if (!$this->storage->has($tag, self::REQUEST_TYPE)) {
                break;
            }
            $max *= 10;
        }

        return $tag;
    }


    /**
     * Saves the transmitted response using a specific key in storage.
     */
    public function setResponse(string $tag, ResponseInterface $response, ServerRequestInterface $request): void
    {
        $this->storage->set(
            $tag, self::RESPONSE_TYPE, (string)json_encode(($this->converter->convertResponseToArray($response, $request)))
        );
    }

    /**
     * Saving current worker data.
     */
    public function setWorkerStat(): void
    {
        $this->storage->set(
            (string)$this->config->getCurrentWorkerId(),
            self::WORKER_TYPE,
            (string)json_encode([
                'start' => $this->config->getStartUnixTime(),
                'lifetime' => $this->config->getWorkerLifetimeSec()
            ])
        );
    }

    /**
     * Returns an iterator of requests matching the worker.
     */
    public function getRequests(): RequestIterator
    {
        return new RequestIterator(
            $this->storage,
            $this->config,
            $this->converter,
            $this->logger
        );
    }

    /**
     * Checking if the current worker is not active or disabled will return false.
     * Not used in worker mode.
     */
    public function isWorkersActive(): bool
    {
        if ($this->config->getWorkerNum() === 0) {
            return false;
        }
        $this->workerActiveId = $this->selectWorkerId();

        if ($this->workerActiveId) {
            return true;
        }
        $this->logger->warning("(S) No active workers found, switch to standard mode.");

        return false;
    }

    /**
     * Create a current request object for special cases.
     */
    public function createCurrentRequest(): ServerRequestInterface
    {
        return $this->converter->convertArrayToServerRequest(
            $this->converter->convertCurrentServerRequestToArray($this->converter->getCurrentRequest())
        );
    }

    /**
     * Returns the original empty successful response object.
     */
    public function createDefaultResponse(): ResponseInterface
    {
        return $this->converter->convertParamsToResponse();
    }

    /**
     * Displays the found response to a Web request.
     * Will be awaiting third party processing.
     * The result will be shutdown or continues execution.
     *
     * @return array<string, mixed>
     */
    public function prepareRequest(string $tag, Output $output): array
    {
        $response = (new ResponseSearchEngine($this->storage, $this->config, $tag))->search();
        if ($response !== null) {
            $message = '(A-2) Completion of processing an asynchronous request: receiving the response generated by the worker...';
            $info = [
                'For worker #' . explode('-', $tag)[0] . ' ' . $message . ' ' . ' {status} {phrase}',
                [
                    'status' => $response['statusCode'],
                    'phrase' => $response['reasonPhrase']
                ]
            ];
            $this->logger->info(...$info);

            $output->run($response);

            $this->logStat($tag);

            return $output->getResult();
        }
        $this->logger->warning('(A->S) The timeout for a response from the worker has expired. Emergency switch to standard mode.');

        return [];
    }

    /**
     * Convert the current request response to array.
     *
     * @return array{
     *          statusCode: int,
     *          body: string,
     *          headers: string[][],
     *          reasonPhrase: string,
     *          version: string,
     *          middleware: array{
     *             session: array{
     *              sessionId: string,
     *              sessionName: string,
     *              session: array<string, mixed>
     *             },
     *             cookie: array<string, array{
     *              name: string,
     *              value: string,
     *              expires: int,
     *              path: string,
     *              domain: string,
     *              secure: bool,
     *              httponly: bool
     *            }>,
     *           }
     *          }
     */
    public function handleResponse(ServerRequestInterface $request, ResponseInterface $response): array
    {
        return $this->converter->convertResponseToArray($response, $request);
    }

    /**
     * Saving the final statistics to the log.
     */
    public function logStat(string $tag, ?float $start = null): void
    {
        if ($start === null) {
            $start = $this->config->getStartUnixTime();
        }
        LoggerManager::logStatistics($tag, $start, $this->config, $this->logger);
    }

    /**
     * Sending a log as a result of the request being processed by the worker.
     */
    public function sendWorkerLog(ServerRequestInterface $request, ResponseInterface $response, string $tag, ?Throwable $t = null): void
    {
        $id = $this->config->getCurrentWorkerId();
        $message = "(A) Worker #{$id}: The request data has been processed {$tag}";
        $logLevel = LogLevel::INFO;
        if ($t) {
            $message = sprintf("(A) Error found by worker #{$id} in request {$tag}: %s in %s:%s", $t->getMessage(), $t->getFile(), $t->getLine());
            $logLevel = LogLevel::WARNING;
        }
        $this->logger->log($logLevel, ...LoggerManager::preparePsr7FromLogger($message, $request, $response));
    }

    /**
     * Sending a log as a result of standard request processing.
     */
    public function sendStandardLog(ServerRequestInterface $request, ResponseInterface $response, ?Throwable $t = null): void
    {
        $message = '(S) The request was processed in standard mode.';
        $logLevel = LogLevel::INFO;
        if ($t) {
            $message = sprintf("(E) Error generating response in standard mode. %s in %s:%s", $t->getMessage(), $t->getFile(), $t->getLine());
            $logLevel = LogLevel::WARNING;
        }
        $this->logger->log($logLevel, ...LoggerManager::preparePsr7FromLogger($message, $request, $response));
    }

    /**
     * Selecting a worker from possible values.
     */
    private function getDistributionId(): int
    {
        if ($this->config->getWorkerNum() === 1) {
            return 1;
        }
        return  $this->selectWorkerId() ?? 1;
    }

    /**
     * Select the active worker or null if one is not found.
     */
    private function selectWorkerId(): ?int
    {
        $workers = range(1, $this->config->getWorkerNum());
        shuffle($workers);
        // The first random active worker is selected.
        foreach ($workers as $number) {
            $data = $this->storage->get((string)$number, self::WORKER_TYPE);
            if (!$data) {
                if (rand(0, 10) === 1) {
                    $this->logger->warning('(S) Worker #{num} not found.', ['num' => $number]);
                }
            } else if ($this->checkWorkerIsAccessible(WorkerHelper::extractWorkerData($data))) {
                $this->logger->debug('(S) Worker #{num} was selected to process the request.', ['num' => $number]);
                return $number;
            }
        }
        return null;
    }

    /**
     * Returns the result of checking that the worker has been active recently.
     *
     * @param array<string, int|float> $workerInfo
     */
    private function checkWorkerIsAccessible(array $workerInfo): bool
    {
        // If the worker started a long time ago, then it is not suitable for selection.
        return (($workerInfo['start'] + (float)$workerInfo['lifetime']) > ($this->config->getStartUnixTime() - 1));
    }
}
