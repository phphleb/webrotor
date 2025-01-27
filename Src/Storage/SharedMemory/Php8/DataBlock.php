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
    /**
     * @var int
     */
    private $shmKey;

    public function __construct(string $key, string $type)
    {
        $this->shmKey = TokenGenerator::createToken(__FILE__, $type, $key);
    }

    /**
     * It is assumed that the value is written once and its size is known.
     */
    public function set(string $value): bool
    {
        $value = trim($value) ?: '[]';
        $length = strlen($value) + 60;
        $umask = umask(0000);
        if ($length < 150) {
            $value = str_pad($value, 150);
            // Use as a counter.
            $id = shmop_open($this->shmKey, 'c', 0666, 200);
        } else {
            // Use as storage.
            $id = @shmop_open($this->shmKey, 'с', 0666, $length);
        }
        umask($umask);
        if ($id) {
            $result = shmop_write($id, $value, 0);
            return $result !== false;
        }
        return false;
    }

    /**
     * It is assumed that the value being requested is one that has a key, so it exists.
     */
    public function get(): ?string
    {
        $id = shmop_open($this->shmKey, 'a', 0, 0);
        $result = null;
        if ($id) {
            $data = shmop_read($id, 0, shmop_size($id));
            if (is_string($data)) {
                $result = trim($data) ?: '[]';
            }
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
            $id = shmop_open($this->shmKey, 'a', 0, 0);
            if ($id) {
                shmop_delete($id);
                if (PHP_VERSION_ID < 80000) {
                    shmop_close($id);
                }
            }
        } catch (\RuntimeException $_e) {
        } finally {
            restore_error_handler();
        }
    }
}
