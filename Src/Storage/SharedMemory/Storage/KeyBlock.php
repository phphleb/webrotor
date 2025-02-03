<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage\SharedMemory\Storage;

use Phphleb\Webrotor\Src\Exception\WebRotorException;
use Phphleb\Webrotor\Src\Storage\SharedMemory\TokenGenerator;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * Implements a separate key store for data.
 */
final class KeyBlock
{
    /**
     * @link https://www.php.net/manual/en/intro.sem.php
     */
    private const SIZE = 100000;

    private const SEG = 0;

    /**
     * @var int
     */
    private $shmKey;

    public function __construct(string $type)
    {
        $this->shmKey = TokenGenerator::createFileToken(__FILE__, $type);
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        $id = $this->open();
        /** @var array<string>|null $keysArray */
        $keysArray = shm_get_var($id, self::SEG);
        $this->close($id);

        return $keysArray ?? [];
    }

    public function has(string $key): ?int
    {
        $id = $this->open();
        $keysArray = shm_get_var($id, self::SEG);
        $this->close($id);

        if (is_array($keysArray) && array_key_exists($key, $keysArray)) {
            return $keysArray[$key];
        }

        return null;
    }

    public function set(string $key, int $token): void
    {
        $id = $this->open();
        $keysArray = shm_get_var($id, self::SEG);

        if (!is_array($keysArray)) {
            $keysArray = [];
        }

        if (array_key_exists($key, $keysArray)) {
            return;
        }
        $keysArray[$key] = $token;

        shm_put_var($id, self::SEG, $keysArray);
        $this->close($id);
    }

    public function delete(string $key): ?int
    {
        $id = $this->open();
        $keysArray = shm_get_var($id, self::SEG);
        if (!is_array($keysArray)) {
            return null;
        }
        if (!array_key_exists($key, $keysArray)) {
            return null;
        }
        $token = $keysArray[$key];
        unset($keysArray[$key]);

        shm_put_var($id,self::SEG, $keysArray);
        $this->close($id);

        return $token;
    }

    /**
     * @return \SysvSharedMemory
     */
    private function open()
    {
        $id = shm_attach($this->shmKey, self::SIZE, 0666);

        if (!$id) {
            throw new WebRotorException('Unable to reserve block in memory segment');
        }
        $existingKeys = null;
        try {
            set_error_handler(static function ($_errno, $errstr) {
                throw new \RuntimeException($errstr);
            });

            $existingKeys = shm_get_var($id, self::SEG);

        } catch (\RuntimeException $_e) {
        } finally {
            restore_error_handler();
        }
        if (!is_array($existingKeys)) {
            shm_put_var($id, self::SEG, []);
        }

        return $id;
    }

    /**
     * @param \SysvSharedMemory $id
     */
    private function close($id): void
    {
        shm_detach($id);
    }
}
