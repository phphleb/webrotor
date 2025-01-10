<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Middleware;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * Processing Cookies to replace the standard synchronous action.
 */
interface CookieMiddlewareInterface
{
    /**
     * When creating a custom handler based on this interface,
     * it is enough to pass its object when initializing
     * the web server without filling in the constructor
     * parameters that will be added later.
     *
     * @param array<string, mixed> $initData
     */
    public function __construct(array $initData = []);

    /**
     * Returns the value by key or default if not found.
     *
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, $default = null);

    /**
     * Returns all installed Cookies in an array [key => value].
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Sets the Cookie value in the PHP format of the setcookie function.
     *
     * @param int|array{
     *              expires?: int,
     *              path?: string,
     *              domain?: string,
     *              secure?: bool,
     *              httponly?: bool
     *            } $expires_or_options
     *
     * @see setcookie()
     */
    public function set(
        string $name,
        string $value = '',
        $expires_or_options = 0,
        string $path = '',
        string $domain = '',
        bool   $secure = false,
        bool   $httponly = false
    ): void;

    /**
     * Returns only data set during the current session [key => [params]].
     *
     * @return array<string, array{
     *                 name: string,
     *                 value: string,
     *                 expires: int,
     *                 path: string,
     *                 domain: string,
     *                 secure: bool,
     *                 httponly: bool
     *               }>
     * @internal
     *
     */
    public function __toArray(): array;
}
