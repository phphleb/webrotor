<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Middleware;

use RuntimeException;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class CookieMiddleware implements CookieMiddlewareInterface
{
    /**
     *
     * @var array<string, array{
     *                name: string,
     *                value: string,
     *                expires: int,
     *                path: string,
     *                domain: string,
     *                secure: bool,
     *                httponly: bool
     *              }>
     *
     */
    private $cookies = [];

    /**
     * @var array<string, mixed>
     */
    private $init;

    #[\Override]
    /**
     * @param array<string, mixed> $initData
     */
    public function __construct(array $initData = [])
    {
        $this->init = $initData;
    }

    /**
     * @inheritDoc
     *
     * Setting a Cookie is similar to the PHP function setcookie.
     */
    #[\Override]
    public function set(
        string $name,
        string $value = "",
        $expires_or_options = 0,
        string $path = "",
        string $domain = "",
        bool   $secure = false,
        bool   $httponly = false
    ): void
    {
        if (is_array($expires_or_options)) {
            $expires_or_options = $expires_or_options['expires'] ?? 0;
            $path = $expires_or_options['path'] ?? '';
            $domain = $expires_or_options['domain'] ?? '';
            $secure = $expires_or_options['secure'] ?? false;
            $httponly = $expires_or_options['httponly'] ?? false;
        }
        $this->cookies[$name] = [
            'name' => $name,
            'value' => $value,
            'expires' => $expires_or_options,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly
        ];

        $_COOKIE[$name] = $value;
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function all(): array
    {
        $result = [];
        foreach ($this->cookies as $cookie) {
            $result[$cookie['name']] = $cookie['value'];
        }
        return array_merge($this->init, $result);
    }

    /**
     * @inheritDoc
     *
     * @param mixed $default
     * @return mixed
     */
    #[\Override]
    public function get(string $name, $default = null)
    {
        return $this->cookies[$name] ?? $this->init[$name] ?? $default;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function __toArray(): array
    {
        return $this->cookies;
    }

    private function __clone()
    {
        throw new RuntimeException("Cloning of this object is prohibited");
    }
}
