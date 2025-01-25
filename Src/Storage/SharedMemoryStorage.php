<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage;

use Phphleb\Webrotor\Src\Exception\WebRotorException;
use Phphleb\Webrotor\Src\Storage\Shmop\DataBlock;
use Phphleb\Webrotor\Src\Storage\Shmop\KeyBlock;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class SharedMemoryStorage implements StorageInterface
{
    /** @var KeyBlock[] */
    private $keyBlocks = [];

    /** @var DataBlock[] */
    private $dataBlocks = [];

    /** @var resource[] */
    private $semaphores = [];

    public function __construct()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            throw new WebRotorException('This `shmop`-based module is not available for Windows');
        }
        if (!function_exists('shmop_open')) {
            throw new WebRotorException('PHP `shmop` extension not installed');
        }
        if (!function_exists('sem_get')) {
            throw new WebRotorException('PHP `sysvsem` extension not installed');
        }
    }

    /** @inheritDoc */
    #[\Override]
    public function get(string $key, string $type): ?string
    {
        $this->acquire($type);
        $data = null;
        if ($this->getKeys($type)->has($key)) {
            $data = $this->getData($key, $type)->get();
        }
        $this->release($type);

        return $data;
    }

    /** @inheritDoc */
    #[\Override]
    public function set(string $key, string $type, string $value): void
    {
        $this->acquire($type);
        $this->getData($key, $type)->set($value);
        $this->getKeys($type)->set($key);
        $this->release($type);
    }

    /** @inheritDoc */
    #[\Override]
    public function delete(string $key, string $type): bool
    {
        $this->acquire($type);
        $this->getKeys($type)->delete($key);
        $this->getData($key, $type)->delete();
        $result = !$this->getKeys($type)->has($key);
        $this->release($type);

        return $result;
    }

    /** @inheritDoc */
    #[\Override]
    public function has(string $key, string $type): bool
    {
        $this->acquire($type);
        $result = $this->getKeys($type)->has($key);
        $this->release($type);

        return $result;
    }

    /** @inheritDoc */
    #[\Override]
    public function keys(string $type): array
    {
        $this->acquire($type);
        $result = $this->getKeys($type)->all();
        $this->release($type);

        return $result;
    }

    private function getKeys(string $type): KeyBlock
    {
        if (!array_key_exists($type, $this->keyBlocks)) {
            $this->keyBlocks[$type] = new KeyBlock($type);
        }
        return $this->keyBlocks[$type];
    }

    private function getData(string $key, string $type): DataBlock
    {
        $tag = $key . '_' . $type;
        if (!array_key_exists($tag, $this->dataBlocks)) {
            $this->dataBlocks[$tag] = new DataBlock($key, $type);
        }
        return $this->dataBlocks[$tag];
    }

    /**
     * @return resource
     */
    private function semaphore(string $type)
    {
        if (!isset($this->semaphores[$type])) {
            $shmKey = ftok(__FILE__ . '_' . $type, 't');
            $semaphore = sem_get($shmKey, 1);
            if (!$semaphore) {
                throw new WebRotorException('Failed to set lock');
            }
            $this->semaphores[$type] = $semaphore;
        }

        return $this->semaphores[$type];
    }

    private function acquire(string $type): void
    {
        sem_acquire($this->semaphore($type));
    }

    private function release(string $type): void
    {
        sem_release($this->semaphore($type));
    }
}
