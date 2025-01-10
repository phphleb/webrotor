<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Middleware;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * Processing Sessions to replace the standard synchronous action.
 */
interface SessionMiddlewareInterface
{
    /**
     * When creating a custom handler based on this interface,
     * it is enough to pass its object when initializing
     * the web server without filling in the constructor
     * parameters that will be added later.
     *
     * @param array<string, mixed> $initData
     */
    public function __construct(string $sessionId = '', string $sessionName = '', array $initData = []);

    /**
     * Returns the current ID of the initialized session.
     */
    public function getSessionId(): ?string;

    /**
     * Returns the name of the initialized session.
     */
    public function getSessionName(): ?string;

    /**
     * Returns the session value by key.
     * If there is no data, it will return the default value.
     *
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Sets the value by key.
     *
     * @param mixed $value
     */
    public function set(string $key, $value): void;

    /**
     * Returns data as a named array [key => value].
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Removes the value by key if found.
     */
    public function delete(string $key): void;

    /**
     * Clear all session data.
     */
    public function clear(): void;

    /**
     * Returns data as a named array.
     *
     * @return array{
     *           sessionId: string,
     *           sessionName: string,
     *           session: array<string, mixed>
     *           }
     * @internal
     *
     * @see self::all()
     */
    public function __toArray(): array;
}
