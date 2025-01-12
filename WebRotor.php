<?php

declare(strict_types=1);

namespace Phphleb\Webrotor;

use Phphleb\Webrotor\Src\Exception\WebRotorConfigException;
use Phphleb\Webrotor\Src\Exception\WebRotorException;
use Phphleb\Webrotor\Src\Handler\Psr7Converter;
use Phphleb\Webrotor\Src\Handler\Psr7CreatorInterface;
use Phphleb\Webrotor\Src\InternalConfig;
use Phphleb\Webrotor\Src\Log\FileLogger;
use Phphleb\Webrotor\Src\Middleware\CookieMiddlewareInterface;
use Phphleb\Webrotor\Src\Middleware\SessionMiddlewareInterface;
use Phphleb\Webrotor\Src\Process\Cleaner;
use Phphleb\Webrotor\Src\Process\Output;
use Phphleb\Webrotor\Src\Process\Spawn\TemporaryWorkerCreator;
use Phphleb\Webrotor\Src\Process\Spawn\TemporaryWorkerCreatorInterface;
use Phphleb\Webrotor\Src\Session\SessionManager;
use Phphleb\Webrotor\Src\Process\Worker;
use Phphleb\Webrotor\Src\Log\LoggerManager;
use Phphleb\Webrotor\Src\Session\SessionManagerInterface;
use Phphleb\Webrotor\Src\Storage\FileStorage;
use Phphleb\Webrotor\Src\Storage\StorageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

$argv = $argv ?? null;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * Implementing an asynchronous web server.
 */
final class WebRotor
{
    public const ID_ARG = '--id';

    public const TEMPORARY_WORKER_ARG = '--temporary-worker';

    public const RUNTIME_DIR = 'wr-runtime';

    public const LOGS_DIR = 'wr-logs';

    /**
     * @var bool
     */
    private $hasInitialized = false;

    /**
     * @var int
     */
    private $executions = 0;

    /**
     * @var InternalConfig
     */
    private $config;

    /**
     * @var StorageInterface|null
     */
    private $storage = null;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Worker
     */
    private $process = null;

    /**
     * @var CookieMiddlewareInterface|null
     */
    private $cookie = null;

    /**
     * @var SessionMiddlewareInterface|null
     */
    private $session = null;

    /**
     * @var Output|null
     */
    private $output = null;

    /**
     * @var null|SessionManagerInterface
     */
    private $sessionManager = null;

    /**
     * @var null|TemporaryWorkerCreatorInterface
     */
    private $workerCreator = null;

    /**
     * @var bool
     */
    private $isWorker;

    /**
     * @var string|null
     */
    private $searchTag = null;

    /**
     * @param Config|null $config - a configuration object with non-standard settings.
     * @param LoggerInterface|null $logger - implementation of a custom logger.
     * @param array<string, array<int, string>|null> $globals - defining additional global variable.
     */
    public function __construct(?Config $config = null, ?LoggerInterface $logger = null, array $globals = [])
    {
        global $argv;
        $arguments = array_key_exists('argv', $globals) ? $globals['argv'] : $argv;
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        if (empty($backtrace[0]['file'])) {
            throw new WebRotorException('Could not determine public project directory');
        }
        $this->isWorker = empty($_SERVER['REQUEST_METHOD']) && is_array($arguments);

        $this->config = $this->initConfig($config ?? new Config(), $backtrace[0]['file'], $arguments);
        $logger = $logger ?? new FileLogger($this->config);
        $this->logger = $logger;
        WebRotorException::$logger = $this->logger;

        register_shutdown_function(static function() use ($logger) {
            $error = error_get_last();
            gc_collect_cycles();
            gc_mem_caches();
            if ($error &&
                (strpos($error['file'], 'webrotor') !== false ||
                    strpos($error['message'], 'Allowed memory size') !== false
                )) {
                $logger->error("{$error['message']} in {$error['file']} on line {$error['line']}");
            }
        });
    }

    /**
     * @param StorageInterface $value - data storage different from the default (in files)
     */
    public function setStorage(StorageInterface $value): self
    {
        if ($this->hasInitialized) {
            throw new WebRotorException('The `setStorage` method cannot be used after initialization');
        }
        $this->storage = $value;

        return $this;
    }

    /**
     * @param CookieMiddlewareInterface $value - custom Cookies handler.
     */
    public function setCookieMiddleware(CookieMiddlewareInterface $value): self
    {
        if ($this->hasInitialized) {
            throw new WebRotorException('The `setCookieMiddleware` method cannot be used after initialization');
        }
        $this->cookie = $value;

        return $this;
    }

    /**
     * @param SessionMiddlewareInterface $value - custom session handler.
     */
    public function setSessionMiddleware(SessionMiddlewareInterface $value): self
    {
        if ($this->hasInitialized) {
            throw new WebRotorException('The `setSessionMiddleware` method cannot be used after initialization');
        }
        $this->session = $value;

        return $this;
    }

    /**
     * @param Output $value - custom result handler.
     */
    public function setOutput(Output $value): self
    {
        if ($this->hasInitialized) {
            throw new WebRotorException('The `setOutput` method cannot be used after initialization');
        }
        $this->output = $value;

        return $this;
    }

    /**
     * @param SessionManagerInterface $sessionManager - custom session handler.
     */
    public function setSessionManager(SessionManagerInterface $sessionManager): self
    {
        if ($this->hasInitialized) {
            throw new WebRotorException('The `setSessionManager` method cannot be used after initialization');
        }
        $this->sessionManager = $sessionManager;

        return $this;
    }

    /**
     * @param TemporaryWorkerCreatorInterface $workerCreator - custom new worker handler.
     */
    public function setWorkerCreator(TemporaryWorkerCreatorInterface $workerCreator): self
    {
        if ($this->hasInitialized) {
            throw new WebRotorException('The `setWorkerCreator` method cannot be used after initialization');
        }
        $this->workerCreator = $workerCreator;

        return $this;
    }

    /**
     * The initialization method for asynchronous processing
     * must be called before initializing your working code (framework).
     *
     * @param Psr7CreatorInterface $psr7Creator - a wrapper for initializing PSR-7 objects.
     *
     * @return array<string, mixed>
     */
    public function init(Psr7CreatorInterface $psr7Creator): array
    {
        $this->hasInitialized = true;
        $storage = $this->storage ?? new FileStorage($this->config->getRuntimeDirectory());
        $this->sessionManager = $this->sessionManager ?? new SessionManager($this->logger, $this->config);
        $workerCreator = $this->workerCreator ?? new TemporaryWorkerCreator($this->config, $this->logger, $storage);

        $this->process = new Worker(
            $this->config,
            $storage,
            new Psr7Converter(
                $psr7Creator,
                $this->cookie,
                $this->session,
                $this->sessionManager
            ),
            $this->logger,
            $this->sessionManager,
            $workerCreator
        );

        if ($this->isWorker) {
            $this->logger->debug(...LoggerManager::createInitialWorkerInfo($this->config));
        }
        if (!$this->isWorker && $this->process->isWorkersActive()) {
            /**
             * @var array<string, null|string> $request
             * @var string $tag
             */
            [$tag, $request] = $this->process->setCurrentRequest();
            $this->searchTag = $tag;
            if (empty($request)) {
                return [];
            }
            $this->logger->info(...LoggerManager::createStartInfoFromLogger($request, $tag));

            return $this->process->prepareRequest($tag, $this->output ?? new Output($this->process->getSessionManager()));
        }
        return [];
    }

    /**
     * External code handler in a function.
     *
     * @return array<string, mixed>
     */
    public function run(callable $fn): array
    {
        $this->executions++;
        if (!$this->hasInitialized) {
            throw new WebRotorException('The `run` method can only be used after initialization');
        }
        $this->output = $this->output ?? new Output($this->process->getSessionManager());
        if ($this->isWorker) {
            $this->process->setWorkerStat();
            (new Cleaner($this->process, $this->config, $this->logger))->cleanOldResources();

            // Processing the requests in worker mode.
            foreach($this->process->getRequests() as $tag => $request) {
                $start = microtime(true);
                try {
                    /**
                     * @var ResponseInterface $response - the result of executing application code.
                     */
                    $response = $fn($request, $this->process->createDefaultResponse());
                    /** @var ServerRequestInterface $request */
                    $this->process->sendWorkerLog($request, $response, $tag);
                } catch (Throwable $t) {
                    $response = $this->process->getErrorResponse($this->config->isDebug() ? (string)$t : '');
                    /** @var ServerRequestInterface $request */
                    $this->process->sendWorkerLog($request, $response, $tag, $t);
                }
                /** @var ServerRequestInterface $request */
                $this->process->setResponse($tag, $response, $request);
                $this->output->setResponse($response);
                $this->process->logStat($tag, $start);
            }
        } else {
            // If the worker was unable to process the request,
            // it is displayed in standard mode.
            $request = $this->process->createCurrentRequest();
            try {
                /**
                 * @var ResponseInterface $response - the result of executing application code.
                 */
                $response = $fn($request, $this->process->createDefaultResponse());

                $this->process->sendStandardLog($request, $response);
            } catch (Throwable $t) {
                $response = $this->process->getErrorResponse($this->config->isDebug() ? (string)$t : '');
                $this->process->sendStandardLog($request, $response, $t);
            }
            $this->process->logStat($this->searchTag ?? 'undefined');
            $this->output->run($this->process->handleResponse($request, $response));
        }
        return $this->output->getResult();
    }

    /**
     * @param array<int, string>|null $arguments
     */
    private function initConfig(Config $config, string $indexFilePath, ?array $arguments): InternalConfig
    {
        $publicDirectory = dirname($indexFilePath);
        $runtimeDirectory = $config->runtimeDirectory;
        $logDirectory = $config->logDirectory;
        if (empty($runtimeDirectory)) {
            $runtimeDirectory = $publicDirectory . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . self::RUNTIME_DIR;
        }
        if (empty($logDirectory)) {
            $logDirectory = $publicDirectory . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . self::LOGS_DIR;
        }

        $isTemporaryWorker = false;
        $interpreterPathPattern = '';
        if ($this->isWorker) {
            if ($config->interpreterPathPattern) {
                $version = preg_replace('/(\d+\.\d+)(\.\d+)?/', '$1', PHP_VERSION);
                if ($version) {
                    $interpreterPathPattern = str_replace('{version}', $version, $config->interpreterPathPattern);
                }
                if (!is_dir($interpreterPathPattern)) {
                    $interpreterPathPattern = '';
                }
            }
            foreach($arguments ?? [] as $arg) {
                if ($arg === self::TEMPORARY_WORKER_ARG) {
                    $isTemporaryWorker = true;
                }
            }
            foreach($arguments ?? [] as $arg) {
                if (strpos($arg, self::ID_ARG . '=') === 0) {
                    $workerId = (int)substr($arg, strlen(self::ID_ARG) + 1);
                    if ((!$isTemporaryWorker && $workerId > $config->workerNum) || $workerId < 1) {
                        throw new WebRotorConfigException('The ID of the current worker is not included in the number set in the configuration');
                    }
                }
            }
        }

        $maxExecutionTime = ini_get('max_execution_time');
        if (!is_numeric($maxExecutionTime)) {
            $maxExecutionTime = $this->isWorker ? 0 : 30;
        }

        return new InternalConfig(
            $indexFilePath,
            microtime(true),
            $config->workerNum,
            $runtimeDirectory,
            $workerId ?? 1,
            $config->workerLifetimeSec,
            (int)$maxExecutionTime,
            $logDirectory,
            $config->logLevel,
            $config->workerResponseTimeSec,
            $config->debug,
            $config->logRotationPerDay,
            $config->timeZone,
            $this->isWorker,
            $config->temporaryWorkerLifetimeSec,
            $isTemporaryWorker,
            $interpreterPathPattern,
            $config->codeVersion
        );
    }
}
