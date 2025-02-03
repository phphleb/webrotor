<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage\SharedMemory\Php8;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class DataBlock
{
    /**
     * @var int
     */
    private $shmKey;

    public function __construct(int $shmKey)
    {
        $this->shmKey = $shmKey;
    }

    /**
     * It is assumed that the value is written once and its size is known.
     */
    public function set(string $value, int $length): bool
    {
        $umask = umask(0000);
        $id = @shmop_open($this->shmKey, 'c', 0666, $length);
        umask($umask);
        if ($id) {
            $result = shmop_write($id, $value, 0);
            if (PHP_VERSION_ID < 80000) {
                shmop_close($id);
            }
            return $result !== false;
        }

        return false;
    }

    /**
     * It is assumed that the value being requested is one that has a key, so it exists.
     */
    public function get(): ?string
    {
        $result = null;
        try {
            set_error_handler(static function ($_errno, $errstr) {
                throw new \RuntimeException($errstr);
            });
            $id = shmop_open($this->shmKey, 'a', 0, 0);
            if ($id) {
                $data = shmop_read($id, 0, shmop_size($id));
                if (is_string($data)) {
                    if ($data !== '' && $data[-1] === ' ') {
                        $data = rtrim($data);
                    }
                    $result = $data ?: '[]';
                }
                if (PHP_VERSION_ID < 80000) {
                    shmop_close($id);
                }
            }
        } catch (\RuntimeException $_e) {
        } finally {
            restore_error_handler();
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
