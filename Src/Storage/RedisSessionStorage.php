<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage;

use Phphleb\Webrotor\Src\Exception\WebRotorException;
use Redis;
use RedisException;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * If your hosting service stores sessions in Redis, you can try using the settings
 * for that connection and create a similar connection.
 */
final class RedisSessionStorage extends RedisStorage
{
    /**
     * @throws RedisException
     */
    public function __construct()
    {
        $handler = ini_get('session.save_handler');
        if ($handler !== 'redis') {
            throw new WebRotorException(
                "RedisSessionStorage requires 'session.save_handler=redis' in php.ini" .
                "Current handler: '{$handler}'"
            );
        }

        $savePath = ini_get('session.save_path');
        if (empty($savePath)) {
            throw new WebRotorException(
                "RedisSessionStorage requires 'session.save_path' to be configured"
            );
        }
        $redis = new Redis();
        $redis->connect($savePath);

        parent::__construct($redis);
    }
}