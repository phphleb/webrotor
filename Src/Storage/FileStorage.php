<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage;

use Phphleb\Webrotor\Src\Exceptions\WebRotorException;
use Phphleb\Webrotor\Src\Exceptions\WebRotorInvalidArgumentException;
use Throwable;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class FileStorage implements StorageInterface
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var array<string, bool>
     */
    private $subdirectoryExists = [];

    public function __construct(string $directory)
    {
        if (!$directory) {
            throw new WebRotorInvalidArgumentException('The directory for storing temporary files is not specified');
        }
        $this->directory = $directory;
    }

    /** @inheritDoc */
    #[\Override]
    public function get(string $key, string $type): ?string
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $key . '.json';
        if (!file_exists($file)) {
            return null;
        }
        try {
            $content = @file_get_contents($file);
        } catch (Throwable $_t) {
            return null;
        }
        return $content ?: null;
    }

    /** @inheritDoc */
    #[\Override]
    public function set(string $key, string $type, string $value): void
    {
        $dir = $this->directory . DIRECTORY_SEPARATOR . $type;
        if (empty($this->subdirectoryExists[$type]) && !is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new WebRotorException(sprintf('Directory "%s" was not created', $dir));
        }
        $this->subdirectoryExists[$type] = true;

        $file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        @file_put_contents($file, $value, LOCK_EX|LOCK_SH);
    }

    /** @inheritDoc */
    #[\Override]
    public function delete(string $key, string $type): void
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $key . '.json';
        if (!file_exists($file)) {
            return;
        }
        try {
           @unlink($file);
        } catch (Throwable $_t) {
            // Has already been deleted.
        }
    }

    /** @inheritDoc */
    #[\Override]
    public function has(string $key, string $type): bool
    {
        return file_exists($this->directory . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $key . '.json');
    }

    /** @inheritDoc */
    #[\Override]
    public function keys(string $type): array
    {
        $dir = $this->directory . DIRECTORY_SEPARATOR . $type;
        if (empty($this->subdirectoryExists[$type])) {
            if (!is_dir($dir)) {
                return [];
            }
            $this->subdirectoryExists[$type] = true;
        }

        $files = scandir($dir);

        $jsonFiles = array_filter((array)$files, static function($file) {
            return pathinfo((string)$file, PATHINFO_EXTENSION) === 'json';
        });

        return array_map(static function($file) {
            return pathinfo((string)$file, PATHINFO_FILENAME);
        }, $jsonFiles);
    }
}
