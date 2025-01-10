<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Handler;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class NyholmPsr7Creator implements Psr7CreatorInterface
{
    /**
     * @var ServerRequestCreator
     */
    private $creator;

    /**
     * @var Psr17Factory
     */
    private $psr17factory;

    public function __construct()
    {
        $this->psr17factory = new Psr17Factory();
        $this->creator = new ServerRequestCreator(
            $this->psr17factory,
            $this->psr17factory,
            $this->psr17factory,
            $this->psr17factory
        );
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getCurrentRequestFromGlobals(): ServerRequestInterface
    {
        return $this->creator->fromGlobals();
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
        $request = $this->creator->fromArrays($server, $headers, $cookie, $get, $post, $files, $body);

        foreach ($attributes as $attribute => $value) {
            $request = $request->withAttribute($attribute, $value);
        }
        return $request->withProtocolVersion($version)
            ->withUri($this->psr17factory->createUri($uri))
            ->withMethod($method)
            ->withBody(Stream::create($body));
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function createResponseFromValues(int $code, array $headers, string $body = ''): ResponseInterface
    {
        $response = $this->psr17factory->createResponse($code);

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $response = $response->withHeader($name, $value);
            }
        }
        return $response->withBody(Stream::create($body));
    }
}
