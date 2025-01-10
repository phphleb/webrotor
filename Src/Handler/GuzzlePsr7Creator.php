<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Handler;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GuzzlePsr7Creator implements Psr7CreatorInterface
{
    /**
     * @inheritDoc
     */
    #[\Override]
    public function getCurrentRequestFromGlobals(): ServerRequestInterface
    {
        return ServerRequest::fromGlobals();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function createRequestFromValues(
        array  $server,
        array  $headers,
        array  $cookie,
        array  $get,
        ?array $post,
        array  $files,
        string $body,
        array  $attributes,
        string $version,
        string $uri,
        string $method
    ): ServerRequestInterface
    {
        $request = new ServerRequest($method, $uri, $headers, $body, $version, $server);

        $request = $request
            ->withQueryParams($get)
            ->withCookieParams($cookie)
            ->withParsedBody($post)
            ->withUploadedFiles($files);

        foreach ($attributes as $attribute => $value) {
            $request = $request->withAttribute($attribute, $value);
        }

        return $request;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function createResponseFromValues(int $code, array $headers, string $body = ''): ResponseInterface
    {
        return new Response($code, $headers, $body);
    }
}
