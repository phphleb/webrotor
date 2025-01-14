<?php
// Contents of your index.php file.
// Laravel 11.x basic initiation example.

use Illuminate\Foundation\Application;
use Illuminate\Http\Response;
use Phphleb\Webrotor\Src\Handler\NyholmPsr7Creator;
use Phphleb\Webrotor\WebRotor;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Http\Request;

// This should be the correct path to the autoload file.
require __DIR__ . '/../vendor/autoload.php';

// Wrapper for PSR-7 HTTP client.
$psr7Creator = new NyholmPsr7Creator();
$server = new WebRotor();
// The web server must be initialized before the framework is initialized.
$server->init($psr7Creator);

$app = require __DIR__ . '/../bootstrap/app.php';
/** @var Application $app */
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// An asynchronous loop for processing requests by the framework.
$server->run(function (ServerRequestInterface $request, ResponseInterface $response) use ($kernel) {
    $_SERVER['X-TIME'] = microtime(true);
    $laravelRequest = Request::create(
        (string)$request->getUri(),
        $request->getMethod(),
        [],
        $_COOKIE,
        $_FILES,
        $_SERVER,
        $request->getBody()
    );
    // Handle the request using the Laravel kernel.
    /** @var Response $laravelResponse */
    $laravelResponse = $kernel->handle($laravelRequest);

    $kernel->terminate($laravelRequest, $laravelResponse);

    foreach ($laravelResponse->headers->all() as $name => $header) {
        $response = $response->withHeader($name, $header);
    }
    $response->getBody()->write($laravelResponse->getContent());

    return $response->withStatus($laravelResponse->getStatusCode());
});
