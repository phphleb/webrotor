<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Session;

interface SessionManagerInterface
{
    /**
     * @author Foma Tuturov <fomiash@yandex.ru>
     *
     * Recreates a session that was previously closed.
     */
    public function restart(string $id, string $name): void;

    /**
     * Starts a session if it is not active.
     * Returns the ID and name of the current/new session.
     *
     * @return array<string, string|false>
     */
    public function start(): array;

    /**
     * Clears and closes the session if it is active.
     */
    public function clean(): void;

    /**
     * Returns the result of checking for session activity.
     */
    public function isActive(): bool;
}
