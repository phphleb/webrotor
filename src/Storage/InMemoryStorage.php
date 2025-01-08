<?php

declare(strict_types=1);

namespace Phphleb\WebRotor\Src\Storage;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class InMemoryStorage implements StorageInterface
{
    /**
     * @var array<string, array<string, string|null>>
     */
    public static $data = [];

    /** @inheritDoc */
    #[\Override]
    public function get(string $key, string $type): ?string
    {
        return self::$data[$type][$key] ?? null;
    }

    /** @inheritDoc */
    #[\Override]
    public function set(string $key, string $type, string $value): void
    {
        self::$data[$type][$key] = $value;
    }

    /** @inheritDoc */
    #[\Override]
    public function delete(string $key, string $type): void
    {
        unset(self::$data[$type][$key]);
    }

    /** @inheritDoc */
    #[\Override]
    public function has(string $key, string $type): bool
    {
        return array_key_exists($key, $this->data[$type] ?? []);
    }

    /** @inheritDoc */
    #[\Override]
    public function keys(string $type): array
    {
        return array_keys($this->data[$type] ?? []);
    }
}
