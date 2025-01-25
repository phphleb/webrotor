<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage\Shmop;

use Phphleb\Webrotor\Src\Exception\WebRotorException;


/**
 * Implements a separate key store for data.
 */
final class KeyBlock
{
    /**
     * @var int
     */
    private $shmKey;

    /**
     * @var resource
     */
    private $shmId;

    public function __construct(string $type)
    {
        $this->shmKey = ftok(__FILE__ . '_' . $type, 't');
        $id = @shmop_open($this->shmKey, "a", 0, 0);
        $size = $id ? shmop_size($id) : 500;
        $this->shmId = shmop_open($this->shmKey, "c", 0766, $size);
        if (!$this->shmId) {
            throw new WebRotorException('Unable to reserve block in memory segment');
        }
        $existingKeys = shmop_read($this->shmId, 0, shmop_size($this->shmId));
        if (strlen($existingKeys) === 0) {
            shmop_write($this->shmId, json_encode([]), 0);
        }
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        $existingKeys = shmop_read($this->shmId, 0, shmop_size($this->shmId));
        $keysArray = json_decode($existingKeys, true);

        return $keysArray !== null ? $keysArray : [];
    }

    public function has(string $key): bool
    {
        $existingKeys = shmop_read($this->shmId, 0,  shmop_size($this->shmId));
        $keysArray = json_decode($existingKeys, true);

        return is_array($keysArray) && in_array($key, $keysArray, true);
    }

    public function set(string $key): void
    {
        $size = shmop_size($this->shmId);
        $existingKeys = shmop_read($this->shmId, 0, $size);
        $keysArray = json_decode($existingKeys, true);

        if (!is_array($keysArray)) {
            $keysArray = [];
        }

        if (!in_array($key, $keysArray, true)) {
            $keysArray[] = $key;
        }

        $newDataSize = strlen(json_encode($keysArray));

        if ($newDataSize >= $size) {
            $size = $newDataSize * 2;

            shmop_close($this->shmId);

            $this->shmId = shmop_open($this->shmKey, "c", 0766, $size);

            if (!$this->shmId) {
                throw new WebRotorException('Unable to reserve block in memory segment after resize');
            }
        }
        shmop_write($this->shmId, json_encode($keysArray), 0);
    }


    public function delete(string $key): void
    {
        $existingKeys = shmop_read($this->shmId, 0,  shmop_size($this->shmId));
        $keysArray = json_decode($existingKeys, true);

        if (is_array($keysArray) && in_array($key, $keysArray, true)) {
            $keysArray = array_diff($keysArray, [$key]);
        }

        shmop_write($this->shmId, json_encode($keysArray), 0);
    }
}
