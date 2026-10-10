<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use RuntimeException;
use Lumynus\Framework\Config;
use Lumynus\Framework\LumaClasses;

final class Memory extends LumaClasses
{
    private string $memoryDir;

    public function __construct()
    {
        $this->memoryDir = Config::projectPath()
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'memory'
            . DIRECTORY_SEPARATOR;

        if (!is_dir($this->memoryDir) && !mkdir($this->memoryDir, 0755, true)) {
            throw new RuntimeException("Failed to create memory directory: {$this->memoryDir}");
        }
    }

    /**
     * Reads a memory file and returns the value.
     *
     * @param string $filename File name (with or without extension)
     *
     * @return mixed|null Returns the original value or null if the file does not exist
     */
    public function read(string $filename): mixed
    {
        $filename = str_ireplace('.luma', '', $filename);

        $path = $this->getPath($filename);
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }

        $value = @unserialize($content, ['allowed_classes' => false]);

        if ($value === false && $content !== serialize(false)) {
            return null;
        }

        return $value;
    }

    /**
     * Saves a PHP value to a memory file.
     *
     * @param string $filename Base file name
     * @param mixed  $value Value to be serialized
     * @param bool   $overwrite Defines whether to overwrite an existing file
     *                           - true: overwrites
     *                           - false: generates a unique name (e.g. name_1.luma)
     *
     * @return string|false Saved file name on success or false on failure
     */
    public function write(string $filename, mixed $value, bool $overwrite = true): string|false
    {
        $finalPath = $overwrite
            ? $this->getPath($filename)
            : $this->getUniquePath($filename);

        $tmpPath = $finalPath . '.' . uniqid('', true) . '.tmp';
        $data = serialize($value);

        if (file_put_contents($tmpPath, $data, LOCK_EX) === false) {
            return false;
        }

        if (!rename($tmpPath, $finalPath)) {
            @unlink($tmpPath);
            return false;
        }

        return basename($finalPath);
    }

    /**
     * Removes a memory file.
     *
     * @param string $filename File name
     *
     * @return bool True if the file was removed, false if it does not exist or fails
     */
    public function delete(string $filename): bool
    {
        $path = $this->getPath($filename);
        return file_exists($path) ? unlink($path) : false;
    }

    /**
     * Removes memory files older than the specified timestamp.
     *
     * @param int $timestamp Timestamp
     *
     * @return void
     */
    public function deleteOlderThan(int $timestamp): void
    {
        foreach ($this->list() as $file) {
            $fileTimestamp = $this->getModified($file)['timestamp'];

            if ($fileTimestamp < $timestamp) {
                $this->delete($file);
            }
        }
    }

    /**
     * Checks if a memory file exists.
     *
     * @param string $filename File name
     *
     * @return bool True if the file exists, false otherwise
     */
    public function has(string $filename): bool
    {
        return file_exists($this->getPath($filename));
    }

    /**
     * Returns the modification date of the memory file.
     *
     * @param string $filename File name
     *
     * @return array Modification date of the file
     */
    public function getModified(string $filename): array
    {
        $path = $this->getPath($filename);

        if (!is_file($path)) {
            throw new RuntimeException("File not found: {$filename}");
        }

        $timestamp = filemtime($path);

        if ($timestamp === false) {
            throw new RuntimeException("Could not get modification time for file: {$filename}");
        }

        return [
            'date' => date('Y-m-d H:i:s', $timestamp),
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Returns the size of the memory file.
     *
     * @param string $filename File name
     *
     * @return int File size
     */
    public function getSize(string $filename): int
    {
        $path = $this->getPath($filename);

        if (!is_file($path)) {
            throw new RuntimeException("File not found: {$filename}");
        }

        $size = filesize($path);

        if ($size === false) {
            throw new RuntimeException("Could not get file size: {$filename}");
        }

        return $size;
    }

    /**
     * Removes all memory files in the storage directory.
     *
     * @return bool True if all files were removed, false if none exist or fails
     */
    public function clear(): bool
    {
        $files = $this->list();
        foreach ($files as $file) {
            $this->delete($file);
        }
        return true;
    }

    /**
     * Lists all memory files present in the storage directory.
     *
     * @return string[] List of memory file names
     */
    public function list(): array
    {
        return array_values(array_map(
            fn($f) => pathinfo($f, PATHINFO_FILENAME),
            array_filter(
                scandir($this->memoryDir),
                fn($f) => preg_match('/\.luma$/i', $f)
            )
        ));
    }

    /**
     * Ensures that the final path is within the storage directory.
     */
    private function getPath(string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        return $this->memoryDir . $filename . '.luma';
    }

    /**
     * Generates a unique path if the file already exists.
     */
    private function getUniquePath(string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->memoryDir . $filename . '.luma';
        $i = 1;

        while (file_exists($path)) {
            $path = $this->memoryDir . $filename . '_' . $i . '.luma';
            $i++;
        }

        return $path;
    }

    /**
     * Removes path traversal and invalid characters.
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove relative paths and subdirectories
        $filename = basename($filename);
        // Replace invalid characters with _
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
    }
}
