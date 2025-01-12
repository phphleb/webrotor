<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Process;

use Phphleb\Webrotor\Src\Exception\WebRotorException;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 *
 * @internal
 */
final class WorkerHelper
{
    /**
     * Sorts the found names into request and response.
     * Leaves only those suitable for the current worker.
     *
     * @param array<int, string> $requestKeys
     * @param array<int, string> $responseKeys
     * @return array<int, array<int, string> >
     */
    public static function sortRawKeys(array $requestKeys, array $responseKeys, int $id): array
    {
        return [self::castIdOnly($requestKeys, $id), self::castIdOnly($responseKeys, $id)];
    }

    /**
     * Universal worker data parsing.
     *
     * @return array<string, int|float>
     */
    public static function extractWorkerData(string $json): array
    {
        if (!$json) {
            throw new WebRotorException('No data for worker');
        }

        $info = json_decode($json, true);

        if (!is_array($info) || count($info) !== 2) {
            throw new WebRotorException('Wrong worker data format');
        }
        return ['start' => (float)$info['start'], 'lifetime' => (int)$info['lifetime']];
    }

    /**
     * Checking if a resource is out of date.
     */
    public static function checkIsOlder(string $key, string $type, float $startTime, ?int $codeVersion = null): bool
    {
        if ($type === Worker::WORKER_TYPE) {
            return false;
        }
        [$_id, $time, $executionTime, $version, $_file] = explode('-', $key);

        if ($codeVersion !== null && (int)$version !== $codeVersion) {
            return true;
        }

        $time = (float)(((int)$time)/1000000);

        return $startTime > ($time + (float)$executionTime);
    }

    /**
     * Normalizes the path without accessing the disk.
     */
    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $segments = explode('/', $path);
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);
            } else {
                $normalized[] = $segment;
            }
        }
        return '/' . trim(implode('/', $normalized), '/');
    }

    /**
     * Checking the tag for the content of the ID being checked.
     *
     * @param array<int, string> $tags
     * @return array<int, string>
     */
    private static function castIdOnly(array $tags, int $id): array
    {
        $result = [];
        foreach ($tags as $tag) {
            if (strpos($tag, $id . '-') === 0) {
                $result[] = $tag;
            }
        }
        return $result;
    }
}
