<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage;

use Phphleb\Webrotor\Src\Exception\WebRotorComplianceException;
use Phphleb\Webrotor\Src\Exception\WebRotorException;
use Phphleb\Webrotor\Src\Storage\SharedMemory\MemorySegment;
use Phphleb\Webrotor\Src\Storage\SharedMemory\Php8\DataBlock;
use Phphleb\Webrotor\Src\Storage\SharedMemory\Php8\KeyBlock;
use Phphleb\Webrotor\Src\Storage\SharedMemory\TokenGenerator;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * Allows an application to store and retrieve data from RAM.
 */
final class SharedMemoryStorage implements StorageInterface
{
    /** @var KeyBlock[] */
    private $keyBlocks = [];

    /** @var DataBlock[] */
    private $dataBlocks = [];

    /** @var array<\SysvSemaphore> */
    private $semaphores = [];

    public function __construct()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            throw new WebRotorComplianceException('This `sysvshm`-based module is not available for Windows');
        }
        if (!extension_loaded('sysvshm')) {
            throw new WebRotorComplianceException('PHP `sysvshm` extension not installed');
        }
        if (!extension_loaded('sysvsem')) {
            throw new WebRotorComplianceException('PHP `sysvsem` extension not installed');
        }
        if (!extension_loaded('shmop')) {
            throw new WebRotorComplianceException('PHP `shmop` extension not installed');
        }
    }

    /** @inheritDoc */
    #[\Override]
    public function get(string $key, string $type): ?string
    {
        $data = null;
        $this->acquire($type);
        $searchId = $this->getKeysManager($type)->has($key);
        $this->release($type);
        if ($searchId !== null) {
            $data = $this->getValuesManager($searchId)->get();
        }

        return $data;
    }

    /** @inheritDoc */
    #[\Override]
    public function set(string $key, string $type, string $value): void
    {
        $value = $value ?: '[]';
        $length = strlen($value) + 60;
        // Worker data.
        if ($length < 150) {
            $value = str_pad($value, 150);
            $length = 200;
        }

        $this->acquire($type);
        $id = $this->getKeysManager($type)->has($key);
        if (!$id) {
            $id = MemorySegment::getFreeSegmentFromShmop($type, $key, $length);
        }
        $this->release($type);
        if ($this->getValuesManager($id)->set($value, $length)) {
            $this->acquire($type);
            $this->getKeysManager($type)->set($key, $id);
            $this->release($type);
        }
    }

    /** @inheritDoc */
    #[\Override]
    public function delete(string $key, string $type): bool
    {
        $this->acquire($type);
        $searchKey = $this->getKeysManager($type)->delete($key);
        $this->release($type);
        if ($searchKey !== null) {
            $this->getValuesManager($searchKey)->delete();
        }
        return !$this->has($key, $type);
    }

    /** @inheritDoc */
    #[\Override]
    public function has(string $key, string $type): bool
    {
        $this->acquire($type);
        $result = $this->getKeysManager($type)->has($key);
        $this->release($type);

        return $result !== null;
    }

    /** @inheritDoc */
    #[\Override]
    public function keys(string $type): array
    {
        $this->acquire($type);
        $keys = $this->getKeysManager($type)->all();
        $this->release($type);

        /** @var array<string> $keys */
        $keys = array_map('strval', array_keys($keys));

        return $keys;
    }

    /**
     * Returns an object associated with the type for working with keys.
     */
    private function getKeysManager(string $type): KeyBlock
    {
        if (!array_key_exists($type, $this->keyBlocks)) {
            $this->keyBlocks[$type] = new KeyBlock($type);
        }
        return $this->keyBlocks[$type];
    }

    /**
     * Returns an object for working with data by type and key.
     */
    private function getValuesManager(int $id): DataBlock
    {
        if (!array_key_exists($id, $this->dataBlocks)) {
            $this->dataBlocks[$id] = new DataBlock($id);
        }
        return $this->dataBlocks[$id];
    }

    /**
     * Returns the semaphore lock by type.
     *
     * @return \SysvSemaphore
     */
    private function semaphore(string $type)
    {
        if (!isset($this->semaphores[$type])) {
            $shmKey = TokenGenerator::createFileToken(__FILE__, $type);
            $semaphore = sem_get($shmKey, 1);
            if (!$semaphore) {
                throw new WebRotorException('Failed to set lock');
            }
            $this->semaphores[$type] = $semaphore;
        }

        return $this->semaphores[$type];
    }

    /**
     * Captures a semaphore for the specified type.
     * Used to synchronize access to a resource.
     */
    private function acquire(string $type): void
    {
        sem_acquire($this->semaphore($type));
    }

    /**
     * Releases a semaphore for the specified type.
     * Must be called after completion of work with the protected resource.
     */
    private function release(string $type): void
    {
        sem_release($this->semaphore($type));
    }
}
