<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage\SharedMemory\Php8;

use Phphleb\Webrotor\Src\Exception\WebRotorException;
use Phphleb\Webrotor\Src\Storage\SharedMemory\TokenGenerator;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class DataBlock
{
    private const SIZE = 256;

    private const SEG = 0;

    /**
     * @var int
     */
    private $shmKey;

    public function __construct(string $key, string $type)
    {
        $this->shmKey = TokenGenerator::createToken('value', $type, $key);
    }

    public function set(string $value): void
    {
        $value = trim($value) ?: '[]';
        $length = (int)(strlen($value) * 1.2);
        $id = $this->getId();
        $realSize = max($length, self::SIZE);
        if ($id) {
            if ($length >= self::SIZE) {
                shm_remove($id);
                $this->close($id);
                $id = shm_attach($this->shmKey, $realSize, 0666);
            }
        } else {
            $id = shm_attach($this->shmKey, $realSize, 0666);
        }
        if ($id) {
            shm_put_var($id, self::SEG, $value);
        } else {
            throw new WebRotorException('Failed to save value to memory segment');
        }
        $this->close($id);
    }

    public function get(): ?string
    {
        $id = $this->getId();
        if ($id && shm_has_var($id, self::SEG)) {
            $data = shm_get_var($id, self::SEG);
            if (is_string($data)) {
                return $data ?: '[]';
            }
            return null;
        }
        return null;
    }

    public function has(): bool
    {
        $id = $this->getId();
        if ($id) {
            return shm_has_var($id, self::SEG);
        }
        return false;
    }

    public function delete(): void
    {
        $id = $this->getId();
        if ($id) {
            shm_remove($id);
            $this->close($id);
        }
    }

    /**
     * @return false|\SysvSharedMemory
     */
    private function getId()
    {
        return shm_attach($this->shmKey, self::SIZE, 0666);
    }

    /**
     * @param \SysvSharedMemory $id
     */
    private function close($id): void
    {
        shm_detach($id);
    }
}
