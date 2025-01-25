<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage\Shmop;

final class DataBlock
{
    /**
     * @var int
     */
    private $shmKey;

    private $minSize = 100;

    public function __construct(string $key, string $type)
    {
        $this->shmKey = ftok(__FILE__ . '_' . $type . '_' . $key, 't');
    }

    public function set(string $value): void
    {
        $length = strlen($value);
        $id = $this->getId();
        $realSize = $this->minSize;
        if ($id) {
            $realSize = shmop_size($id);
            if ($length !== $realSize) {
                $this->delete();
                $realSize = max($length, $this->minSize);
            }
       }
        $id = shmop_open($this->shmKey, "c", 0666, $realSize);

        shmop_write($id, $value, 0);
    }

    public function get(): ?string
    {
        $id = $this->getId();
        $data = null;
        if ($id) {
            $data = @shmop_read($id, 0, shmop_size($id)) ?: null;
        }

        return $data;
    }

    public function has(): bool
    {
        return $this->getId() !== false;
    }

    public function delete(): void
    {
        $id = $this->getId();
        if ($id) {
            @shmop_delete($id);
            @shmop_close($id);
        }
    }

    /**
     * @return false|resource
     */
    private function getId()
    {
        return @shmop_open($this->shmKey, "a", 0, 0);
    }
}
