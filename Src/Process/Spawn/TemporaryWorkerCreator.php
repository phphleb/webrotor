<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Process\Spawn;

use Phphleb\Webrotor\Src\InternalConfig;
use Phphleb\Webrotor\Src\Process\Worker;
use Phphleb\Webrotor\Src\Storage\StorageInterface;
use Phphleb\Webrotor\WebRotor;
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
        if (!$interpreter || !is_dir($interpreter)) {
            $this->logger->warning('The path for the PHP interpreter in the configuration is incorrect');
            return;
        }
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
                } catch (Throwable $t) {
                    $this->logger->warning('The additional worker failed to start. ' . $t->getMessage());
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
