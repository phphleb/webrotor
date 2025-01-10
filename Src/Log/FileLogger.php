<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Log;

use DateTime;
use DateTimeZone;
use Exception;
use Phphleb\Webrotor\Src\Exceptions\WebRotorException;
use Phphleb\Webrotor\Src\InternalConfig;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class FileLogger extends AbstractLogger
{
    /**
     * @var InternalConfig
     */
    private $config;

    /**
     * @var bool
     */
    private $logDirExists = false;

    public function __construct(InternalConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = []): void
    {
        $currentLevel = $this->config->getLogLevel();
        $logDirectory = $this->config->getLogDirectory();

        $levels = [
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT => 1,
            LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3,
            LogLevel::WARNING => 4,
            LogLevel::NOTICE => 5,
            LogLevel::INFO => 6,
            LogLevel::DEBUG => 7,
        ];

        if (!isset($levels[$level]) || $levels[$level] > $levels[$currentLevel]) {
            return;
        }

        if (!$this->logDirExists && !is_dir($logDirectory) && !@mkdir($logDirectory, 0777, true) && !is_dir($logDirectory)) {
            throw new WebRotorException(sprintf('Directory "%s" was not created', $logDirectory));
        }
        $this->logDirExists = true;

        try {
            $timezone = new DateTimeZone($this->config->getTimeZone());

            $date = (new DateTime("now", $timezone))->format('Y-m-d');
            $logFile = $logDirectory . DIRECTORY_SEPARATOR . 'wr_' . $date . '.log';

            $formattedMessage = sprintf(
                "[%s] %s: %s",
                (new DateTime("now", $timezone))->format('Y-m-d H:i:s'),
                strtoupper($level),
                $this->interpolate($message, $context)
            );
        }  catch (Exception $e) {
            throw new WebRotorException((string)$e);
        }
        file_put_contents($logFile, $formattedMessage . PHP_EOL, FILE_APPEND);
    }

    /**
     * Interpolates context data into a message.
     *
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }
        return strtr($message, $replacements);
    }

}
