<?php
// Contents of your index.php file.
// Connecting to Redis using the PHP `predis` extension.

use Phphleb\Webrotor\Src\Handler\NyholmPsr7Creator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Phphleb\Webrotor\WebRotor;
use Phphleb\Webrotor\Src\Storage\RedisStorage;

require __DIR__ . '/../vendor/autoload.php';

$redis = new \Redis();
try {
    $redis->connect('127.0.0.1', 6379, 2);
 // $redis->auth($auth); // Optional
} catch (\RedisException $e) {
    throw new RuntimeException("Redis connection failed");
}
$storage = new RedisStorage($redis);

$psr7Creator = new NyholmPsr7Creator();
$server = (new WebRotor())->setStorage($storage);
$server->init($psr7Creator);

$server->run(function(ServerRequestInterface $request, ResponseInterface $response) {

    $response->getBody()->write('I use Redis as storage!');

    return $response;
});
