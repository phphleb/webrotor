<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage\SharedMemory;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class MemorySegment
{
    public static function getFreeSegmentFromShmop(string $type, string $key, int $length): int
    {
        $defaultKey = TokenGenerator::createToken(__FILE__, $type, $key);
        while (true) {
            $searchId = false;
            try {
                set_error_handler(static function ($_errno, $errstr) {
                    throw new \RuntimeException($errstr);
                });
                $umask = umask(0000);
                $searchId = shmop_open($defaultKey, 'n', 0666, $length);
                umask($umask);
            } catch (\RuntimeException $_e) {
            } finally {
                restore_error_handler();
            }
            if (!$searchId) {
                $defaultKey++;
                continue;
            }
            if (PHP_VERSION_ID < 80000) {
                shmop_close($searchId);
            }
            return $defaultKey;
        }
    }

}
