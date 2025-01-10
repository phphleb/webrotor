<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * Psr-7 supported library initializer.
 */
interface Psr7CreatorInterface
{
    /**
     * Create a request object from the current global data.
     *
     * @return ServerRequestInterface
     */
    public function getCurrentRequestFromGlobals(): ServerRequestInterface;

    /**
     * Create a new request object from the data.
     *
     * @param array<string, mixed> $server
     * @param array<string, string[]> $headers
     * @param array<string, mixed> $cookie
     * @param array<string, mixed> $get
     * @param array<string, mixed>|null $post
     * @param array<string, mixed> $files
     * @param string $body
     * @param array<string, mixed> $attributes
     * @param string $version
     * @param string $uri
     * @param string $method
     * @return ServerRequestInterface
     */
    public function createRequestFromValues(
        array   $server,
        array   $headers,
        array   $cookie,
        array   $get,
        ?array  $post,
        array   $files,
        string  $body,
        array   $attributes,
        string  $version,
        string  $uri,
        string  $method
    ): ServerRequestInterface;

    /**
     * Create a new response object from the data.
     *
     * @param int $code
     * @param array<string, string[]> $headers
     * @param string $body
     * @return ResponseInterface
     */
    public function createResponseFromValues(int $code, array $headers, string $body = ''): ResponseInterface;
}
