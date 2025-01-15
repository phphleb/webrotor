<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Storage;

use Phphleb\Webrotor\Src\Exception\WebRotorException;
use Phphleb\Webrotor\Src\Exception\WebRotorInvalidArgumentException;
use RuntimeException;

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
        $content = null;
        try {
            set_error_handler(static function ($_errno, $errstr) {
                throw new RuntimeException($errstr);
            });

            $content = file_get_contents($file);

        } catch (RuntimeException $_e) {

        } finally {
            restore_error_handler();
        }
        return $content ?: null;
    }

    /** @inheritDoc */
    #[\Override]
    public function set(string $key, string $type, string $value): void
    {
        $umask = @umask(0000);
        $dir = $this->directory . DIRECTORY_SEPARATOR . $type;
        try {
            set_error_handler(static function ($_errno, $errstr) {
                throw new RuntimeException($errstr);
            });
            if (empty($this->subdirectoryExists[$type]) && !is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new WebRotorException(sprintf('Directory "%s" was not created', $dir));
            }
        } catch (RuntimeException $_e) {
            // Created by a parallel process.
        } finally {
            restore_error_handler();
        }
        $this->subdirectoryExists[$type] = true;

        $file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        @file_put_contents($file, $value, LOCK_EX);
        clearstatcache(true, $file);

        is_int($umask) and @umask($umask);
    }

    /** @inheritDoc */
    #[\Override]
    public function delete(string $key, string $type): bool
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $key . '.json';
        try {
            set_error_handler(static function ($_errno, $errstr) {
                throw new RuntimeException($errstr);
            });

            unlink($file);

        } catch (RuntimeException $_e) {
            // Has already been deleted.
            return false;
        } finally {
            restore_error_handler();
            clearstatcache(true, $file);
        }
        return true;
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

        if (!($files = @scandir($dir))) {
            return [];
        }

        $jsonFiles = array_filter((array)$files, static function ($file) {
            return pathinfo((string)$file, PATHINFO_EXTENSION) === 'json';
        });

        return array_map(static function ($file) {
            return pathinfo((string)$file, PATHINFO_FILENAME);
        }, $jsonFiles);
    }
}
