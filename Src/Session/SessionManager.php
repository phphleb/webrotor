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

    /**
     * @var InternalConfig
     */
    private $config;

    public function __construct(LoggerInterface $logger, InternalConfig $config)
    {
        $this->logger = $logger;
        $this->label = $config->isWorker() ? '(W) ' : '(S) ';
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function restart(string $id, string $name): void
    {
        if (!$id || !$name) {
            return;
        }
        $defaultId = '';
        $sessionName = '';
        if ($this->isActive()) {
            $defaultId = session_id();
            $sessionName = session_name();
        }
        if (!$defaultId || !$sessionName) {
            return;
        }
        if ($defaultId !== $id || $sessionName !== $name) {
            $this->clean();
            session_id($id);
            session_name($name);
            $this->start();
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
        $sessionId = $this->config->isDebug() ? @session_id() : '=hidden=';
        $sessionName = @session_name();
        // If you need to start a session, then you need to do this separately.
        $this->logger->debug(
            $this->label . 'Session data at the start of processing: {session_id:{id} session_name:{name}, active:{active}}',
            ['id' => $sessionId, 'name' => $sessionName, 'active' => (int)$this->isActive()]);

        return ['session_id' => $sessionId, 'session_name' => $sessionName];
    }


    /** @inheritDoc */
    #[\Override]
    public function clean(): void
    {
        if ($this->isActive()) {
            // This function is used to close the current session.
            @session_write_close();
        }
    }

    /** @inheritDoc */
    #[\Override]
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
