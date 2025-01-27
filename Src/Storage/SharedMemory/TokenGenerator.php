<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage\SharedMemory;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class TokenGenerator
{
    public static function createToken(string $file, string $type, string $key = ''): int
    {
        $input = $file . '~' . $key . '~' . $type;

        return abs(crc32($input));
    }

    public static function createFileToken(string $file, string $type): int
    {
        $project = substr($type, -1);

        return ftok($file, $project);
    }

}
