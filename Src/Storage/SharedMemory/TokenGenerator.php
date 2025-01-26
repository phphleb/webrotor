<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage\SharedMemory;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class TokenGenerator
{
  public static function createToken(string $prefix, string $type, string $key = ''): int
  {
      $input = $prefix . '~' . $key . '~' . $type;

      return (int)hexdec(substr(md5($input), 0, 8));
  }
}
