<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Session;

use Phphleb\Webrotor\Src\InternalConfig;
use Psr\Log\LoggerInterface;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * Working with sessions in separate methods.
 */
final class SessionManager implements SessionManagerInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $label;

    public function __construct(LoggerInterface $logger, InternalConfig $config)
    {
        $this->logger = $logger;
        $this->label = $config->isWorker() ? '(W) ' : '(S) ';
    }

    /** @inheritDoc */
    #[\Override]
    public function restart(string $id, string $name): void
    {
        if ($id && $name && !$this->isActive()) {
            session_id($id);
            session_name($name);
            session_start();
            $this->logger->debug($this->label . 'Restart session.');
        }
    }

    /**
     * @inheritDoc
     *
     * @return array<string, string|false>
     */
    #[\Override]
    public function start(): array
    {
        if (!$this->isActive()) {
            session_start();
            $this->logger->debug($this->label . 'Start a session because it is not active.');
        }
        return ['session_id' => session_id(), 'session_name' => session_name()];
    }


    /** @inheritDoc */
    #[\Override]
    public function clean(): void
    {
        if ($this->isActive()) {
            session_unset();
            session_destroy();
            $this->logger->debug($this->label . 'Destroying an active session.');
        }
    }

    /** @inheritDoc */
    #[\Override]
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
