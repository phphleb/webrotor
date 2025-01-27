<?php
// Contents of your index.php file.
// Connecting to a shared in-memory data store using the `shmop`, `sysvshm` and `sysvsem` PHP extensions.
// Not supported for Windows.

use Phphleb\Webrotor\Src\Handler\NyholmPsr7Creator;
use Phphleb\Webrotor\Src\Storage\SharedMemoryStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Phphleb\Webrotor\WebRotor;

// This should be the correct path to the autoload file.
require __DIR__ . '/../vendor/autoload.php';

$storage = new SharedMemoryStorage();

$psr7Creator = new NyholmPsr7Creator();
$server = (new WebRotor())->setStorage($storage);

// Web server initialization should come before the rest of the development code.
$server->init($psr7Creator);

$server->run(function(ServerRequestInterface $request, ResponseInterface $response) {
    // ... //
    $response->getBody()->write('I store data in shared RAM!');

    return $response;
});
