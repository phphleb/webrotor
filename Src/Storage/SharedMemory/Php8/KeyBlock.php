<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage\SharedMemory\Php8;

use Phphleb\Webrotor\Src\Exception\WebRotorException;
use Phphleb\Webrotor\Src\Storage\SharedMemory\TokenGenerator;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * Implements a separate key store for data.
 */
final class KeyBlock
{
    private const SIZE = 256;

    private const SEG = 0;

    /**
     * @var int
     */
    private $shmKey;

    public function __construct(string $type)
    {
        $this->shmKey = TokenGenerator::createToken('keys', $type);
    }

    /**
     * @return array<string>
     */
    public function all(): array
    {
        $id = $this->open();
        /** @var array<string>|null $keysArray */
        $keysArray = shm_get_var($id, self::SEG);
        $this->close($id);

        return $keysArray ?? [];
    }

    public function has(string $key): bool
    {
        $id = $this->open();
        $keysArray = shm_get_var($id, self::SEG);
        $this->close($id);

        return is_array($keysArray) && in_array($key, $keysArray, true);
    }

    public function set(string $key): void
    {
        $id = $this->open();
        $keysArray = shm_get_var($id, self::SEG);

        if (!is_array($keysArray)) {
            $keysArray = [];
        }

        if (!in_array($key, $keysArray, true)) {
            $keysArray[] = $key;
        }

        $size = count($keysArray) * 100;

        if ($size >= self::SIZE) {
            shm_remove($id);
            $this->close($id);

            $id = $this->open($size);
        }
        shm_put_var($id, self::SEG, $keysArray);
        $this->close($id);
    }

    public function delete(string $key): void
    {
        $id = $this->open();
        $keysArray = shm_get_var($id, self::SEG);

        if (is_array($keysArray) && in_array($key, $keysArray, true)) {
            $keysArray = array_diff($keysArray, [$key]);
        }

        shm_put_var($id,self::SEG, $keysArray);
        $this->close($id);
    }

    /**
     * @param int $size
     * @return \SysvSharedMemory
     */
    private function open(int $size = self::SIZE)
    {
        $id = shm_attach($this->shmKey, $size, 0666);

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
