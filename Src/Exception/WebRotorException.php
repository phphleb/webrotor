<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Exception;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * The main class of web server errors includes logging.
 */
class WebRotorException extends RuntimeException
{
    /** @var null|LoggerInterface */
    public static $logger = null;




    /**
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $logMessage = sprintf(
            "Exception: %s in %s on line %d",
            $message,
            $this->getFile(),
            $this->getLine()
        );

        self::$logger and self::$logger->error($logMessage);
    }
}
