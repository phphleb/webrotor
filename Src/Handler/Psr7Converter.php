<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Handler;

use Phphleb\Webrotor\Src\Middleware\CookieMiddleware;
use Phphleb\Webrotor\Src\Middleware\CookieMiddlewareInterface;
use Phphleb\Webrotor\Src\Middleware\SessionMiddleware;
use Phphleb\Webrotor\Src\Middleware\SessionMiddlewareInterface;
use Phphleb\Webrotor\Src\Session\SessionManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class Psr7Converter
{
    /**
     * @var CookieMiddlewareInterface|null
     */
    private $cookie;

    /**
     * @var SessionMiddlewareInterface|null
     */
    private $session;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var Psr7CreatorInterface
     */
    private $psr7Creator;

    /**
     * @var ServerRequestInterface|null
     */
    private $currentRequest = null;

    public function __construct(
        Psr7CreatorInterface        $psr7Creator,
        ?CookieMiddlewareInterface  $cookie,
        ?SessionMiddlewareInterface $session,
        SessionManagerInterface     $sessionManager
    )
    {
        $this->cookie = $cookie;
        $this->session = $session;
        $this->sessionManager = $sessionManager;
        $this->psr7Creator = $psr7Creator;
    }

    /**
     * Returns the currently relevant request object.
     */
    public function getCurrentRequest(): ?ServerRequestInterface
    {
        return $this->currentRequest;
    }

    /**
     * Returns the converted data of the current request into a standardized request array.
     *
     * @return array{
     *      serverParams: array<string, mixed>,
     *      headers: string[][],
     *      body: string,
     *      version: string,
     *      uri: string,
     *      method: string,
     *      attributes: array{
     *          session: array<string, mixed>,
     *          sessionId: string,
     *          sessionName: string,
     *          cookie: array<string, mixed>,
     *          get: array<string, mixed>,
     *          post: array<string, mixed>,
     *          env: array<string, mixed>,
     *          files: array<string, mixed>
     *      }
     *  }
     */
    public function convertCurrentServerRequestToArray(?ServerRequestInterface $request = null): array
    {
        if (!$request) {
            $sessionInfo = $this->sessionManager->start();
            $request = $this->psr7Creator->getCurrentRequestFromGlobals()
                ->withAttribute('sessionId', $sessionInfo['session_id'])
                ->withAttribute('sessionName', $sessionInfo['session_name'])
                ->withAttribute('session', $_SESSION ?? [])
                ->withAttribute('cookie', $_COOKIE)
                ->withAttribute('files', $_FILES)
                ->withAttribute('post', $_POST)
                ->withAttribute('get', $_GET)
                ->withAttribute('env', $_ENV);
        }
        $this->currentRequest = $request;

        /**
         * @var array{
         *       serverParams: array<string, mixed>,
         *       headers: string[][],
         *       body: string,
         *       version: string,
         *       uri: string,
         *       method: string,
         *       attributes: array{
         *           session: array<string, mixed>,
         *           sessionId: string,
         *           sessionName: string,
         *           cookie: array<string, mixed>,
         *           get: array<string, mixed>,
         *           post: array<string, mixed>,
         *           env: array<string, mixed>,
         *           files: array<string, mixed>
         *       }
         *   } $result
         */
        $result =  [
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri(),
            'headers' => $request->getHeaders(),
            'body' => (string)$request->getBody(),
            'attributes' => $request->getAttributes(),
            'serverParams' => $request->getServerParams(),
            'version' => $request->getProtocolVersion()
        ];

        return $result;
    }

    /**
     * Converts a standardized request array to a server-side request object.
     *
     * @param array{
     *     serverParams: array<string, mixed>,
     *     headers: string[][],
     *     body: string,
     *     version: string,
     *     uri: string,
     *     method: string,
     *     attributes: array{
     *         session: array<string, mixed>,
     *         sessionId: string,
     *         sessionName: string,
     *         cookie: array<string, mixed>,
     *         get: array<string, mixed>,
     *         post: array<string, mixed>,
     *         env: array<string, mixed>,
     *         files: array<string, mixed>
     *     }
     * } $request
     */
    public function convertArrayToServerRequest(array $request): ServerRequestInterface
    {
        $_GET = $_POST = $_REQUEST = $_SERVER = $_FILES = [];
        $_SESSION = $_COOKIE = [];

        $attributes = $request['attributes'];
        $requestObject = $this->psr7Creator->createRequestFromValues(
            $request['serverParams'],
            $request['headers'],
            $attributes['cookie'],
            $attributes['get'],
            $attributes['post'],
            $attributes['files'],
            $request['body'],
            $request['attributes'],
            $request['version'],
            $request['uri'],
            $request['method']
        );

        $_GET = $attributes['get'];
        $_SERVER = $request['serverParams'];
        $_POST = $attributes['post'];
        $_COOKIE = $attributes['cookie'];
        $_REQUEST = array_merge($_GET, $_POST);
        $_SESSION = $attributes['session'];
        $_FILES = $attributes['files'];
        $_ENV = $attributes['env'];

        $session = [
            $attributes['sessionId'],
            $attributes['sessionName'],
            $attributes['session']
        ];
        if ($this->session) {
            $sessionClass = get_class($this->session);
            $this->session = new $sessionClass(...$session);
        } else {
            $this->session = new SessionMiddleware(...$session);
        }
        if ($this->cookie) {
            $cookieClass = get_class($this->cookie);
            $this->cookie = new $cookieClass($attributes['cookie']);
        } else {
            $this->cookie = new CookieMiddleware($attributes['cookie']);
        }
        return $requestObject
            ->withAttribute('cookie', $this->cookie)
            ->withAttribute('session', $this->session);
    }

    /**
     * Converts the response object to a standardized response array.
     *
     * @return array{
     *          statusCode: int,
     *          body: string,
     *          headers: string[][],
     *          reasonPhrase: string,
     *          version: string,
     *          middleware: array{
     *             session: array{
     *            sessionId: string,
     *            sessionName: string,
     *            session: array<string, mixed>
     *            },
     *             cookie: array<string, array{
     *                 name: string,
     *                 value: string,
     *                 expires: int,
     *                 path: string,
     *                 domain: string,
     *                 secure: bool,
     *                 httponly: bool
     *               }>,
     *           }
     *          }
     */
    public function convertResponseToArray(ResponseInterface $response, ServerRequestInterface $request): array
    {
        /**
         * @var SessionMiddlewareInterface $sessionMiddleware
         */
        $sessionMiddleware = $request->getAttribute('session');
        $session = $sessionMiddleware->__toArray();
        /**
         * @var CookieMiddlewareInterface $cookiesMiddleware
         */
        $cookiesMiddleware = $request->getAttribute('cookie');
        $cookies = $cookiesMiddleware->__toArray();

        if ($_SESSION) {
            /** @var array<string, mixed> $sessionMerging */
            $sessionMerging = array_merge($_SESSION, (array)$session['session']);
            $session['session'] = $sessionMerging;
        }
        return [
            'statusCode' => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'request' => [
                'method' => $request->getMethod(),
                'uri' => (string)$request->getUri(),
            ],
            'headers' => $response->getHeaders(),
            'body' => (string)$response->getBody(),
            'version' => $response->getProtocolVersion(),
            'middleware' => [
                'session' => $session,
                'cookie' => $cookies
            ]
        ];
    }

    /**
     * Converts a standardized response array to a response object.
     *
     * @param array{
     *           statusCode: int,
     *           body: string,
     *           headers: string[][],
     *           reasonPhrase: string,
     *           version: string,
     *           middleware: array{
     *              session: array{
     *             sessionId: string,
     *             sessionName: string,
     *             session: array<string, mixed>
     *             },
     *              cookie: array<string, array{
     *                  name: string,
     *                  value: string,
     *                  expires: int,
     *                  path: string,
     *                  domain: string,
     *                  secure: bool,
     *                  httponly: bool
     *                }>,
     *            }
     *           } $response
     */
    public function convertResponseArrayToResponse(array $response): ResponseInterface
    {
        return $this->psr7Creator->createResponseFromValues(
            $response['statusCode'],
            $response['headers'],
            $response['body']
        );

    }

    /**
     * Converts an error encountered while processing user code into a response object.
     */
    public function convertThrowableToResponse(string $error): ResponseInterface
    {
        return $this->psr7Creator->createResponseFromValues(500, [], $error ?: 'Something Went Wrong!');
    }

    /**
     * Creating a response object from the data.
     *
     * @param string[][] $headers
     */
    public function convertParamsToResponse(int $code = 200, array $headers = [], string $body = ''): ResponseInterface
    {
        return $this->psr7Creator->createResponseFromValues($code, $headers, $body);
    }
}
