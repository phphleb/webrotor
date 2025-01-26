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
    private const SEGMENT = 0;

    /**
     * @var int
     */
    private $shmKey;

    public function __construct(string $key, string $type)
    {
        $this->shmKey = TokenGenerator::createToken('value', $type, $key);
    }

    /**
     * It is assumed that the value is written once and its size is known.
     */
    public function set(string $value): void
    {
        $value = trim($value) ?: '[]';
        $length = strlen($value) + 20;
        $umask = umask(0000);
        $id = shm_attach($this->shmKey, $length, 0666);
        umask($umask);
        if ($id) {
            shm_put_var($id, self::SEGMENT, $value);
            shm_detach($id);
            return;
        }
        throw new WebRotorException('Failed to save value to memory segment');
    }

    /**
     * It is assumed that the value being requested is one that has a key, so it exists.
     */
    public function get(): ?string
    {
        $id = shm_attach($this->shmKey, null);
        $result = null;
        if ($id) {
            if (shm_has_var($id, self::SEGMENT)) {
                $data = shm_get_var($id, self::SEGMENT);
                if (is_string($data)) {
                    $result = $data ?: '[]';
                }
            }
            shm_detach($id);
        }
        return $result;
    }

    /**
     * Delete only for existing data.
     */
    public function delete(): void
    {
        try {
            set_error_handler(static function ($_errno, $errstr) {
                throw new \RuntimeException($errstr);
            });
            $id = shm_attach($this->shmKey, null);
            if ($id) {
                shm_remove($id);
                shm_detach($id);
            }
        } catch (\RuntimeException $_e) {
        } finally {
            restore_error_handler();
        }
    }
}
