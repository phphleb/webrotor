<?php

declare(strict_types=1);

namespace Phphleb\Webrotor;

use Psr\Log\LogLevel;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *     
 * Configuration class for overriding all or some default settings.
 * Application example:
 *
 * ```php
 * $config = new Config();
 * $config->workerLifetimeSec = 60;
 *
 * $server = new WebRotor($config);
 * ```
 */
final class Config
{
    /**
     * Debug mode status.
     * Must be disabled on a public server.
     *
     * @var bool
     */
    public $debug = false;

    /**
     * Number of running workers.
     * Must match the actual number of workers,
     * for example, those launched via cron.
     * If it is 0, then it works in normal
     * non-synchronous mode.
     *
     * @var non-negative-int
     */
    public $workerNum = 1;

    /**
     * The value must match the frequency of the worker launch.
     * Recommended value is 60 seconds.
     *
     * @var positive-int
     */
    public $workerLifetimeSec = 60;

    /**
     * Specifies the number of seconds after which the worker will shut down
     * if no requests have been received during this period.
     * If all workers go idle ahead of the scheduled restart, the application
     * will operate without asynchronous processing until the next worker run.
     * If the value is set to 0, no early shutdown will occur.
     *
     * @var non-negative-int
     */
    public $idleTimeoutSec = 0;

    /**
     * Specifies the full path to the directory
     * for storing dynamic script data,
     * if it differs from the default.
     * (!) Should not be in a public directory.
     * You need write permissions for the web server
     * in this directory.
     *
     * @var null|string
     */
    public $runtimeDirectory = null;

    /**
     * Full path to the directory with web server logs.
     * Only for default file logs.
     *
     * @var null|string
     */
    public $logDirectory = null;

    /**
     * Logging level according to PSR-3
     *
     * @var string
     */
    public $logLevel = LogLevel::ERROR;

    /**
     * Maximum time to wait for a response from a worker in seconds.
     * After this, the pending request will be processed as usual.
     * The lower the value, the more likely it is that
     * the request data may be processed twice.
     * If 0 then it will wait for a response until a timeout error occurs.
     *
     * @var int
     */
    public $workerResponseTimeSec = 5;

    /**
     * Sets the day before which logs should be deleted.
     * If 0 then there will be no log rotation.
     * Only for default file logs.
     *
     * @var int
     */
    public $logRotationPerDay = 7;

    /**
     * The time zone value for the web server.
     * If an empty string is passed,
     * the value will be obtained automatically.
     *
     * @var string
     */
    public $timeZone = 'UTC';

    /**
     * The lifetime of the temporary worker in seconds.
     * If 0 is specified, then the value is equal to the main worker lifetime.
     *
     * @var int
     */
    public $temporaryWorkerLifetimeSec = 60;

    /**
     * The delay for the worker while waiting for requests in microseconds.
     * The delay until the next search for unprocessed requests by the worker
     * if they were missing depends on this value.
     * If 0 is specified, the worker will not pause while waiting.
     *
     * @var int
     */
    public $workerRequestDelayMicroSec = 100;


    /**
     * The delay for the process while waiting for response in microseconds.
     * This value determines the interval at which the worker’s response will be checked.
     * If 0 is specified, the process will not pause while waiting.
     *
     * @var int
     */
    public $responseDelayWaitMicroSec = 10;

    /**
     * To create temporary workers, you need to run them with the correct interpreter.
     * This path will be searched and {version}
     * will be replaced with the current version.
     * If the line is empty, temporary workers will not be able
     * to be created and will be disabled.
     *
     * @var string
     */
    public $interpreterPathPattern = '/usr/local/bin/php{version}';

    /**
     * When sending new code to the server, simply increment this value
     * by one and the old running workers will not process the new code.
     *
     * @var int
     */
    public $codeVersion = 1;
}
