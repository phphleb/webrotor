<?php

declare(strict_types=1);

namespace Phphleb\WebRotor\Src\Middlewares;

use RuntimeException;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class SessionMiddleware implements SessionMiddlewareInterface
{
    /**
     * @var array<string, mixed>
     */
    private $session;

    /**
     * @var string
     */
    private $sessionId;

    /**
     * @var string
     */
    private $sessionName;

    /**
     * @inheritDoc
     */
    #[\Override]
    public function __construct(string $sessionId = '', string $sessionName = '', array $initData = [])
    {
        $this->sessionId = $sessionId;
        $this->sessionName = $sessionName;
        $this->session = $initData;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getSessionId(): ?string
    {
        return $this->sessionId ?: null;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getSessionName(): ?string
    {
        return $this->sessionName ?: null;
    }

    /**
     * @inheritDoc
     *
     * @param mixed $default
     * @return mixed
     */
    #[\Override]
    public function get(string $key, $default = null)
    {
        return $this->session[$key] ?? $default;
    }

    /**
     * @inheritDoc
     *
     * @param mixed $value
     */
    #[\Override]
    public function set(string $key, $value): void
    {
        $this->session[$key] = $value;
        $_SESSION[$key] = $value;
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function all(): array
    {
        return array_merge($_SESSION, $this->session);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function delete(string $key): void
    {
        unset($this->session[$key], $_SESSION[$key]);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function clear(): void
    {
        $this->session = $_SESSION = [];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function __toArray(): array
    {
        return [
            'sessionId' => $this->sessionId,
            'sessionName' => $this->sessionName,
            'session' => $this->session,
        ];
    }

    private function __clone()
    {
        throw new RuntimeException("Cloning of this object is prohibited");
    }
}
