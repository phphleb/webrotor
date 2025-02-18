<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage;

use Redis;
use RedisException;

/**
 * Storage using Redis Hash for optimization.
 * Replaces storing values as strings with hashes.
 *
 * Author: Foma Tuturov <fomiash@yandex.ru>
 */
final class RedisStorage implements StorageInterface
{
    /**
     * @var Redis - Redis connection instance
     */
    private $redis;

    /**
     * The `phpredis` extension must be installed.
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @inheritDoc
     *
     * @throws RedisException
     */
    public function get(string $key, string $type): ?string
    {
        // Using Redis hash.
        $value = $this->redis->hGet($type, $key);

        return $value !== false ? (string)$value : null;
    }

    /**
     * @inheritDoc
     *
     * @throws RedisException
     */
    public function set(string $key, string $type, string $value): void
    {
        // Set the value in the hash.
        $this->redis->hSet($type, $key, $value);
    }

    /**
     * @inheritDoc
     *
     * @throws RedisException
     */
    public function delete(string $key, string $type): bool
    {
        // Delete the key from the hash.
        $result = (int)$this->redis->hDel($type, $key);

        return $result > 0;
    }

    /**
     * @inheritDoc
     *
     * @throws RedisException
     */
    public function has(string $key, string $type): bool
    {
        // Check the existence of the key in the hash.
        return (bool)$this->redis->hExists($type, $key);
    }

    /**
     * @inheritDoc
     *
     * @throws RedisException
     */
    public function keys(string $type): array
    {
        // Get all keys in the hash.
        return (array)$this->redis->hKeys($type);
    }

    /**
     * Get all values in the hash.
     *
     * @param string $type Data type (hash name).
     *
     * @return array Array of all values.
     * @throws RedisException
     */
    public function values(string $type): array
    {
        return (array)$this->redis->hVals($type);
    }

    /**
     * Delete the entire hash (type).
     *
     * @param string $type Hash name.
     *
     * @return bool Deletion result.
     * @throws RedisException
     */
    public function deleteHash(string $type): bool
    {
        return (int)$this->redis->del($type) > 0;
    }
}
