<?php

declare(strict_types=1);

namespace Phphleb\WebRotor\Src\Process;

use Phphleb\WebRotor\Src\Session\SessionManagerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * Implements the ability to override data output.
 */
class Output
{
    /**
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    public function __construct(SessionManagerInterface $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    /**
     * Can return an array with response data
     * when overridden, or an empty array.
     *
     * @return array<string, mixed>
     */
    public function getResult(): array
    {
        return [];
    }

    /**
     * Saving processed response.
     */
    public function setResponse(ResponseInterface $response): void
    {
        // To test requests processed by a worker.
    }

    /**
     * Overriding this method will make it possible to run code
     * that depends on it in test mode.
     * The test result can be returned as a string or an array of data.
     *
     * @param array{
     *        statusCode: int,
     *        body: string,
     *        headers: string[][],
     *        reasonPhrase: string,
     *        version: string,
     *        middleware: array{
     *           session: array{
     *            sessionId: string,
     *            sessionName: string,
     *            session: array<string, mixed>,
     *           },
     *           cookie: array<string, array{
     *            name: string,
     *            value: string,
     *            expires: int,
     *            path: string,
     *            domain: string,
     *            secure: bool,
     *            httponly: bool,
     *          }>
     *         }
     *        } $response
     *
     * @return void
     */
    public function run(array $response): void
    {
        http_response_code($response['statusCode']);
        foreach ($response['headers'] as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        $session = $response['middleware']['session'];
        $cookies = $response['middleware']['cookie'];

        $this->sessionManager->restart((string)($session['sessionId']), (string)($session['sessionName']));

        $_SESSION = $session['session'];

        if ($cookies) {
            $global = [];
            foreach ($cookies as $cookie) {
                $global[$cookie['name']] = $cookie['value'];
                setcookie(
                    $cookie['name'],
                    $cookie['value'],
                    $cookie['expires'],
                    $cookie['path'],
                    $cookie['domain'],
                    $cookie['secure'],
                    $cookie['httponly']
                );
            }
            $_COOKIE = $global;
        }

        exit((string)$response['body']);
    }
}
