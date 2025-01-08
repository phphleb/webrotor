<?php

declare(strict_types=1);

namespace Phphleb\WebRotor\Src;

use Phphleb\WebRotor\Src\Exceptions\WebRotorConfigException;
use Phphleb\WebRotor\Src\Exceptions\WebRotorFileSecurityException;
use Phphleb\WebRotor\Src\Process\WorkerHelper;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * @internal
 */
final class InternalConfig
{
    private const WORKER_MIN_LIFETIME = 1;

    /**
     * @var string
     */
    private $indexFilePath;

    /**
     * @var float
     */
    private $startUnixTime;

    /**
     * @var int
     */
    private $workerNum;

    /**
     * @var int
     */
    private $currentWorkerId;

    /**
     * @var string
     */
    private $runtimeDirectory;

    /**
     * @var int
     */
    private $workerLifetimeSec;

    /**
     * @var int
     */
    private $maxExecutionTime;

    /**
     * @var string
     */
    private $logDirectory;

    /**
     * @var string
     */
    private $logLevel;

    /**
     * @var int
     */
    private $workerResponseTimeSec;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var int
     */
    private $logRotationPerDay;

    /**
     * @var string
     */
    private $timeZone;

    /**
     * @var bool
     */
    private $isWorker;

    public function __construct(
        string $indexFilePath,
        float  $startUnixTime,
        int    $workerNum,
        string $runtimeDirectory,
        int    $currentWorkerId,
        int    $workerLifetimeSec,
        int    $maxExecutionTime,
        string $logDirectory,
        string $logLevel,
        int    $workerResponseTimeSec,
        bool   $debug,
        int    $logRotationPerDay,
        string $timeZone,
        bool   $isWorker
    )
    {
        if ($workerNum < 0) {
            throw new WebRotorConfigException('The number of workers must not be a negative number');
        }
        if ($workerLifetimeSec < self::WORKER_MIN_LIFETIME) {
            throw new WebRotorConfigException('The worker lifetime should not be less than 1 second');
        }
        if (!$runtimeDirectory) {
            throw new WebRotorConfigException('The directory for storing dynamic files is not specified');
        }
        if (!$logDirectory) {
            throw new WebRotorConfigException('Logging directory not specified');
        }
        if ($logRotationPerDay < 0) {
            throw new WebRotorConfigException('The log rotation value cannot be a negative number.');
        }
        if ($workerResponseTimeSec < 0) {
            throw new WebRotorConfigException('The worker wait time cannot be a negative number.');
        }
        if ($timeZone === '') {
            $timeZone = date_default_timezone_get();
        }

        $indexDirectory = WorkerHelper::normalizePath(dirname($indexFilePath));
        $runtimeDirectory = WorkerHelper::normalizePath($runtimeDirectory);
        $logDirectory = WorkerHelper::normalizePath($logDirectory);

        if (strpos($runtimeDirectory, $indexDirectory) === 0) {
            throw new WebRotorFileSecurityException("Runtime directory cannot be inside the index file directory");
        }
        if (strpos($logDirectory, $indexDirectory) === 0) {
            throw new WebRotorFileSecurityException("Log directory cannot be inside the index file directory");
        }
        if ($workerResponseTimeSec <= 0 || $workerResponseTimeSec > $maxExecutionTime) {
            $workerResponseTimeSec = $maxExecutionTime;
        }
        if ($isWorker && $maxExecutionTime && $maxExecutionTime < $workerLifetimeSec) {
            throw new WebRotorConfigException('The worker lifetime from the config is greater than the PHP max_execution_time value');
        }

        $this->startUnixTime = $startUnixTime;
        $this->indexFilePath = $indexFilePath;
        $this->workerNum = $workerNum;
        $this->runtimeDirectory = $runtimeDirectory;
        $this->currentWorkerId = $currentWorkerId;
        $this->workerLifetimeSec = $workerLifetimeSec;
        $this->maxExecutionTime = $maxExecutionTime;
        $this->logDirectory = $logDirectory;
        $this->logLevel = $logLevel;
        $this->workerResponseTimeSec = $workerResponseTimeSec;
        $this->debug = $debug;
        $this->logRotationPerDay = $logRotationPerDay;
        $this->timeZone = $timeZone;
        $this->isWorker = $isWorker;
    }

    /**
     * Returns the path to the index file of the current request.
     *
     * @internal
     */
    public function getIndexFilePath(): string
    {
        return $this->indexFilePath;
    }

    /**
     * Returns the time when the web server started processing
     * the request in UNIX format.
     *
     * @internal
     */
    public function getStartUnixTime(): float
    {
        return $this->startUnixTime;
    }

    /**
     * Returns the number of specified workers in the configuration.
     *
     * @internal
     */
    public function getWorkerNum(): int
    {
        return $this->workerNum;
    }

    /**
     * Returns the path to the web server dynamic files directory
     * specified in the configuration.
     *
     * @internal
     */
    public function getRuntimeDirectory(): string
    {
        return $this->runtimeDirectory;
    }

    /**
     * Returns the current worker ID (relevant only in worker mode).
     *
     * @internal
     */
    public function getCurrentWorkerId(): int
    {
        return $this->currentWorkerId;
    }

    /**
     * Returns the worker lifetime set in the configuration.
     *
     * @internal
     */
    public function getWorkerLifetimeSec(): int
    {
        return $this->workerLifetimeSec;
    }

    /**
     * Returns the current max_execution_time value from the PHP settings.
     * Not relevant in worker mode.
     *
     * @internal
     */
    public function getMaxExecutionTime(): int
    {
        return $this->maxExecutionTime;
    }

    /**
     * Returns the path to the directory with web server logs
     * specified in the configuration.
     *
     * @internal
     */
    public function getLogDirectory(): string
    {
        return $this->logDirectory;
    }

    /**
     * Returns the logging level specified in the configuration for the web server.
     *
     * @internal
     */
    public function getLogLevel(): string
    {
        return $this->logLevel;
    }

    /**
     * Maximum time to wait for a response from a worker in seconds.
     *
     * @return int
     *
     * @internal
     */
    public function getWorkerResponseTimeSec(): int
    {
        return $this->workerResponseTimeSec;
    }

    /**
     * Debug mode status.
     * Must be disabled on a public server.
     *
     * @return bool
     *
     * @internal
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * The number of days before which the logs will be deleted.
     *
     * @return int
     */
    public function getLogRotationPerDay(): int
    {
        return $this->logRotationPerDay;
    }

    /**
     * The time zone value for the web server.
     *
     * @return string
     */
    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    /**
     * Returns the result of checking
     * the current request as a worker.
     *
     * @return bool
     */
    public function isWorker(): bool
    {
        return $this->isWorker;
    }
}
