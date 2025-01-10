<?php

declare(strict_types=1);

namespace Phphleb\WebRotor\Src\Storage;

use Redis;
use RedisException;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class RedisStorage implements StorageInterface
{
    /**
     * @var Redis - экземпляр подключения к Redis
     */
    private $redis;

    /**
     * (!) The `phpredis` extension must be installed.
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
        $namespacedKey = $this->buildKey($key, $type);

        /** @var string|null|false $value */
        $value = $this->redis->get($namespacedKey);

        return $value ? (string)$value : null;
    }

    /**
     * @inheritDoc
     *
     * @throws RedisException
     */
    public function set(string $key, string $type, string $value): void
    {
        $namespacedKey = $this->buildKey($key, $type);

        $this->redis->set($namespacedKey, $value);
    }

    /**
     * @inheritDoc
     *
     * @throws RedisException
     */
    public function delete(string $key, string $type): void
    {
        $namespacedKey = $this->buildKey($key, $type);

        $this->redis->del($namespacedKey);
    }

    /**
     * @inheritDoc
     *
     * @throws RedisException
     */
    public function has(string $key, string $type): bool
    {
        $namespacedKey = $this->buildKey($key, $type);

        return !empty($this->redis->exists($namespacedKey));

    }

    /**
     * @inheritDoc
     *
     * @throws RedisException
     */
    public function keys(string $type): array
    {
        $pattern = $this->buildKey('*', $type);
        $keys = $this->redis->keys($pattern);

        // Only real keys without namespace are returned.
        return array_map(static function ($key) use ($type) {
            return str_replace($type . ':', '', $key);
        }, $keys);
    }

    /**
     * Generates a composite key.
     */
    private function buildKey(string $key, string $type): string
    {
        return $type . ':' . $key;
    }
}
