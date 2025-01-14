<?php
// Contents of your index.php file.
// Basic example for displaying the greeting line.

use Phphleb\Webrotor\Src\Handler\GuzzlePsr7Creator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Phphleb\Webrotor\WebRotor;

// This should be the correct path to the autoload file.
require __DIR__ . '/../vendor/autoload.php';

// Wrapper for PSR-7 HTTP client.
$psr7Creator = new GuzzlePsr7Creator();
$server = new WebRotor();

// Web server initialization should come before the rest of the development code.
$server->init($psr7Creator);

// Asynchronous cycle of processing code of the framework/application.
$server->run(function(ServerRequestInterface $request, ResponseInterface $response) {
    // ... //
    $response->getBody()->write('Hello World!');

    return $response;
});
