<?php

declare(strict_types=1);

namespace Phphleb\WebRotor;

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
     * Points to the directory with web server logs.
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
}
