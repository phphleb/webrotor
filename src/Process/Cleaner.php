<?php

declare(strict_types=1);

namespace Phphleb\WebRotor\Src\Process;

use Phphleb\WebRotor\Src\InternalConfig;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
class Cleaner
{
    /**
     * @var Worker
     */
    private $worker;

    /**
     * @var InternalConfig
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Worker $worker, InternalConfig $config, LoggerInterface $logger)
    {
        $this->worker = $worker;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Removing various outdated resources such as worker data and logs.
     */
    public function cleanOldResources(): void
    {
        if ($this->config->isTemporaryWorker()) {
            return;
        }
        if (rand(0, $this->config->getWorkerNum() + 1) === 1) {
            $this->logger->debug('(A) Starting the process of searching for outdated data.');
            $start = microtime(true);
            $this->cleanResources();
            $this->cleanInactiveWorkers();
            $end = microtime(true) - $start;
            $this->logger->debug("(A) Completing the process of searching and removing obsolete data ({$end}s).");
        }
        $interval = 60 * 60 * 2;
        if ($this->config->getWorkerLifetimeSec() >= $interval ||
            rand(0, (int)ceil($interval / $this->config->getWorkerLifetimeSec())) === 1) {
            $this->cleanOldLogs();
        }
    }

    /**
     * Clearing invalid data for requests and responses.
     */
    private function cleanResources(): void
    {
        $startTime = $this->config->getStartUnixTime();
        foreach ([Worker::REQUEST_TYPE, Worker::RESPONSE_TYPE] as $type) {
            foreach ($this->worker->getStorage()->keys($type) as $key) {
                if (WorkerHelper::checkIsOlder($key, $type, $startTime)) {
                    $this->logger->debug('(A) Expired content removed {type} {key}', ['type' => $type, 'key' => $key]);
                    try {
                        $this->worker->getStorage()->delete($key, $type);
                    } catch (Throwable $_t) {
                        // Was deleted by another worker.
                    }
                }
            }
        }
    }

    /**
     * Clear data for inactive workers.
     */
    private function cleanInactiveWorkers(): void
    {
        foreach ($this->worker->getStorage()->keys(Worker::WORKER_TYPE) as $key) {
            $info = $this->worker->getStorage()->get($key, Worker::WORKER_TYPE);
            if ($info) {
                $data = WorkerHelper::extractWorkerData($info);
                $start = $data['start'];
                $lifetime = $data['lifetime'];
                 if ($start + (float)$lifetime < $this->config->getStartUnixTime() - ($lifetime * 2.5)) {
                     $this->worker->getStorage()->delete($key, Worker::WORKER_TYPE);
                     $startData = date('d-m-Y H:i:s', (int)$start);
                     $stat = "(start: {$startData}, lifetime: {$lifetime}s)";
                     $this->logger->info("Worker #{$key} data was deleted because it had not been started for a long time {$stat}.");
                 }
            }
        }
    }

    /**
     * Осуществляет ротацию файловых логов согласно настройкам.
     */
    private function cleanOldLogs(): void
    {
        $logDirectory = $this->config->getLogDirectory();
        $logRotationPerDay = $this->config->getlogRotationPerDay();

        if (!is_dir($logDirectory)) {
            return;
        }

        $files = scandir($logDirectory);
        $thresholdDate = strtotime("-{$logRotationPerDay} days");
        $result = 0;
        /** @var array<int, string> $files */
        foreach ($files as $file) {
            if (preg_match('/^wr_(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
                $fileDate = strtotime($matches[1]);
                if ($fileDate < $thresholdDate) {
                    unlink($logDirectory . '/' . $file);
                    $result++;
                }
            }
        }
        $result and $this->logger->debug('(A) The logs for the files have been rotated.');
    }
}
