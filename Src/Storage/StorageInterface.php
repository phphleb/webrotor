<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
interface StorageInterface
{
    /**
     * Returns the contents by key.
     *
     * @param string $key - a unique key to search for a value.
     * @param string $type - points to a partition, table or directory.
     * @return null|string - returns null if value not found.
     */
    public function get(string $key, string $type): ?string;

    /**
     * Adding or updating data by key.
     *
     * @param string $key - unique key to store the value.
     * @param string $type - points to a partition, table or directory.
     * @param string $value - value to be saved.
     * @return void
     */
    public function set(string $key, string $type, string $value): void;

    /**
     * Delete by key.
     *
     * @param string $key - a unique key that will be used to perform the deletion.
     * @param string $type - points to a partition, table or directory.
     * @return bool - the result of the deletion.
     */
    public function delete(string $key, string $type): bool;

    /**
     * Checking the existence of a key.
     *
     * @param string $key - a unique key for checking existence.
     * @param string $type - points to a partition, table or directory.
     * @return bool - a sign of the existence of a value.
     */
    public function has(string $key, string $type): bool;

    /**
     * Returns an array of existing keys.
     *
     * @param string $type - points to a partition, table or directory.
     * @return string[]
     */
    public function keys(string $type): array;
}
