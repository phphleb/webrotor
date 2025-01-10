<?php

declare(strict_types=1);

namespace Phphleb\WebRotor\Src\Process\Spawn;

use Phphleb\WebRotor\Src\InternalConfig;
use Phphleb\WebRotor\Src\Process\Worker;
use Phphleb\WebRotor\Src\Storage\StorageInterface;
use Phphleb\WebRotor\WebRotor;
use Psr\Log\LoggerInterface;
use Throwable;

final class TemporaryWorkerCreator implements TemporaryWorkerCreatorInterface
{
    private const EXEC_FUNCTIONS = ['exec', 'shell_exec'];

    /**
     * @var InternalConfig
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StorageInterface
     */
    private $storage;

    public function __construct(InternalConfig $config, LoggerInterface $logger, StorageInterface $storage)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->storage = $storage;
    }

    /** @inheritDoc */
   #[\Override]
    public function createWorker(): void
    {
        $interpreter = $this->config->getInterpreterPathPattern();
        $workerId = $this->config->getCurrentWorkerId();
        $newWorkerId = $workerId * 100 + rand(0, 99);
        if ($newWorkerId > 100000) {
            return;
        }
        $tempLifeTime = $this->config->getTemporaryWorkerLifetimeSec();
        $indexPath = $this->config->getIndexFilePath();
        $idTag = WebRotor::ID_ARG;
        $tempTag = WebRotor::TEMPORARY_WORKER_ARG;

        if ($this->storage->has((string)$newWorkerId, Worker::WORKER_TYPE)) {
            $this->logger->debug(
                "(W) A worker with the same ID #{id} already exists. Cancel the creation of a temporary worker.",
                ['id' => $newWorkerId]
            );
            return;
        }

        $command = "$interpreter $indexPath $idTag=$newWorkerId $tempTag > /dev/null 2>&1 &";

        foreach(self::EXEC_FUNCTIONS as $fun) {
            if ($this->isFunctionEnabled($fun)) {
                try {
                    $fun($command);
                    $this->logger->info(
                        "(W) Attempt to create an additional temporary ({lifetime}s) worker #{id}",
                    ['lifetime' => $tempLifeTime, 'id' => $newWorkerId]);
                    if ($this->storage->get((string)$newWorkerId, Worker::WORKER_TYPE)) {
                        $this->logger->info("(W) Failed to create worker #{id}", ['id' => $newWorkerId]);
                    }
                } catch (Throwable $_t) {
                }
                break;
            }
        }
    }

    private function isFunctionEnabled(string $functionName): bool
    {
        if (!function_exists($functionName)) {
            return false;
        }
        $disabledFunctions = explode(',', (string)ini_get('disable_functions'));

        return !in_array($functionName, $disabledFunctions, true);
    }
}