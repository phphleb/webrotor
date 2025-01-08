<?php
// Contents of your index.php file.
// Basic example for displaying the greeting line.

use Phphleb\WebRotor\Src\Handler\GuzzlePsr7Creator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Phphleb\WebRotor\WebRotor;

require __DIR__ . '/../vendor/autoload.php';

$psr7Creator = new GuzzlePsr7Creator();
$server = new WebRotor();
$server->init($psr7Creator);

// Asynchronous cycle of processing code of the framework/application.
$server->run(function(ServerRequestInterface $request, ResponseInterface $response) {
    // ... //
    $response->getBody()->write('Hello World!');

    return $response;
});