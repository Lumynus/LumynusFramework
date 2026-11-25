<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;
use Lumynus\Framework\Logs;
use Lumynus\Framework\Config;

class File extends LumaClasses
{

    /**
     * Reads content from a file in project files directory.
     * @param string $fileName Name of file to read.
     * @param string $path Relative path inside files directory to read file from (optional).
     * @return string|null Returns file content as string, or null on error.
     */
    public function read(string $fileName, string $path = ''): ?string
    {
        try {
            $file = $this->resolveFilePath($fileName, $path);
            if (!is_file($file)) {
                Logs::register('File not found: ' . $fileName, 'error');
                return null;
            }
            return file_get_contents($file);
        } catch (\Throwable $th) {
            Logs::register('File read error: ' . $th->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Renames a file in the project files directory.
     *
     * @param string $fileName Name of the file to rename.
     * @param string $newName New name for the file.
     * @param string $path Relative path inside the files directory.
     * @param bool $overwrite Whether an existing destination may be replaced.
     * @return bool True when the file was renamed successfully.
     */
    public function rename(string $fileName, string $newName, string $path = '', bool $overwrite = false): bool
    {
        try {
            $oldFile = $this->resolveFilePath($fileName, $path);
            $target = $this->resolveTargetPath($newName, $path, $overwrite);

            if (!is_file($oldFile)) {
                Logs::register('File not found: ' . $fileName, 'error');
                return false;
            }

            if ($target === null) {
                Logs::register('File destination already exists: ' . $newName, 'error');
                return false;
            }

            return rename($oldFile, $target);
        } catch (\Throwable $th) {
            Logs::register('File rename error: ' . $th->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Compatibility alias for rename().
     *
     * @param string $fileName Name of the file to rename.
     * @param string $newName New name for the file.
     * @param string $path Relative path inside the files directory.
     * @param bool $overwrite Whether an existing destination may be replaced.
     * @return bool True when the file was renamed successfully.
     * @deprecated Use rename() instead.
     */
    public function replace(string $fileName, string $newName, string $path = '', bool $overwrite = false): bool
    {
        return $this->rename($fileName, $newName, $path, $overwrite);
    }

    /**
     * Checks whether a file exists in project files directory.
     * @param string $fileName Name of file to check.
     * @param string $path Relative path inside files directory to check file in (optional).
     * @return bool Returns true if file exists, false otherwise.
     */
    public function exists(string $fileName, string $path = ''): bool
    {
        try {
            return is_file($this->resolveFilePath($fileName, $path));
        } catch (\Throwable $th) {
            Logs::register('File exists error: ' . $th->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Deletes a file from project files directory.
     * @param string $fileName Name of file to delete.
     * @param string $path Relative path inside files directory to delete file from (optional).
     * @return bool Returns true if file was deleted successfully, false otherwise.
     */
    public function delete(string $fileName, string $path = ''): bool
    {
        try {
            $file = $this->resolveFilePath($fileName, $path);

            if (!is_file($file)) {
                Logs::register('File not found: ' . $fileName, 'error');
                return false;
            }

            return unlink($file);
        } catch (\Throwable $th) {
            Logs::register('File delete error: ' . $th->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Saves an uploaded file to project files directory.
     * @param string $from Temporary uploaded file path. @example: $file['file']['tmp_name']
     * @param string $newName Target file name when saved.
     * @param string $path Relative path inside files directory to save file in (optional).
     * @param bool $overwrite Whether an existing destination may be replaced.
     * @return bool Returns true if file was saved successfully, false otherwise.
     */
    public function save(string $from, string $newName, string $path = '', bool $overwrite = false): bool
    {
        try {
            $target = $this->resolveTargetPath($newName, $path, $overwrite);
            if ($target === null) {
                Logs::register('File destination already exists: ' . $newName, 'error');
                return false;
            }
            return move_uploaded_file($from, $target);
        } catch (\Throwable $th) {
            Logs::register('File upload error: ' . $th->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Copies a file in project files directory.
     * @param string $fileName Name of file to copy.
     * @param string $newName New name for copied file.
     * @param string $path Relative path inside files directory to copy file to (optional).
     * @param bool $overwrite Whether an existing destination may be replaced.
     * @return bool Returns true if file was copied successfully, false otherwise.
     */
    public function copy(string $fileName, string $newName, string $path = '', bool $overwrite = false): bool
    {
        try {
            $source = $this->resolveFilePath($fileName, $path);
            $target = $this->resolveTargetPath($newName, $path, $overwrite);
            if ($target === null) {
                Logs::register('File destination already exists: ' . $newName, 'error');
                return false;
            }
            return copy($source, $target);
        } catch (\Throwable $th) {
            Logs::register('File copy error: ' . $th->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Searches files in project files directory, filtering by file name if key is provided.
     * @param string $key Search key to filter files by name.
     * @param string $path Relative path inside files directory to search in (optional).
     * @return array
     */
    public function search(string $key = '', string $path = ''): array
    {
        try {
            $pathAbsolute = $this->getPath($path);

            if (!is_dir($pathAbsolute)) {
                return [];
            }

            $files = array_diff(
                scandir($pathAbsolute, SCANDIR_SORT_DESCENDING),
                ['.', '..']
            );

            $key = Sanitizer::string($key, false);
            if (!empty($key)) {
                $files = array_filter($files, function ($file) use ($key) {
                    return str_contains($file, $key);
                });
            }

            return array_values($files);
        } catch (\Throwable $th) {
            Logs::register('File search error: ' . $th->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Gets absolute path to project files directory based on given relative path.
     * @param string $path Relative path inside files directory (optional).
     * @return string Absolute path to files directory.
     */
    public function getPath(string $path = ''): string
    {
        $projectPath = Config::projectPath();
        $pathFiles = (string) (Config::getApplicationConfig()['path']['files'] ?? '');
        $basePath = realpath($projectPath . $pathFiles);

        if ($basePath === false || !is_dir($basePath)) {
            throw new \RuntimeException('The project files directory does not exist.');
        }

        $segments = $this->relativeSegments($path);
        $currentPath = $basePath;

        foreach ($segments as $segment) {
            $currentPath .= DIRECTORY_SEPARATOR . $segment;
            if (file_exists($currentPath)) {
                $resolvedPath = realpath($currentPath);
                if ($resolvedPath === false || !$this->isWithin($basePath, $resolvedPath) || !is_dir($resolvedPath)) {
                    throw new \RuntimeException('The requested files path is outside the project files directory.');
                }
                $currentPath = $resolvedPath;
            }
        }

        return rtrim($currentPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Resolves a safe file path within the configured project files directory.
     *
     * @param string $fileName File name.
     * @param string $path Relative directory inside the files directory.
     * @return string Absolute file path.
     */
    private function resolveFilePath(string $fileName, string $path): string
    {
        $this->validateFileName($fileName);
        $directory = $this->getPath($path);
        $file = $directory . $fileName;

        if (file_exists($file)) {
            $resolvedFile = realpath($file);
            $basePath = realpath(Config::projectPath() . (string) (Config::getApplicationConfig()['path']['files'] ?? ''));
            if ($resolvedFile === false || $basePath === false || !$this->isWithin($basePath, $resolvedFile)) {
                throw new \RuntimeException('The requested file is outside the project files directory.');
            }
            return $resolvedFile;
        }

        return $file;
    }

    /**
     * Resolves a safe destination and rejects silent overwrites.
     *
     * @param string $fileName Destination file name.
     * @param string $path Relative directory inside the files directory.
     * @param bool $overwrite Whether an existing regular file may be replaced.
     * @return string|null Destination path, or null when overwrite is disabled.
     */
    private function resolveTargetPath(string $fileName, string $path, bool $overwrite): ?string
    {
        $this->validateFileName($fileName);
        $directory = $this->getPath($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new \RuntimeException('Failed to create the destination directory.');
        }
        $target = $directory . $fileName;

        if (is_link($target)) {
            throw new \RuntimeException('The destination cannot be a symbolic link.');
        }

        if (!file_exists($target)) {
            return $target;
        }

        if (!is_file($target)) {
            throw new \RuntimeException('The destination is not a regular file.');
        }

        if (!$overwrite) {
            return null;
        }

        $resolvedTarget = realpath($target);
        $basePath = realpath(Config::projectPath() . (string) (Config::getApplicationConfig()['path']['files'] ?? ''));
        if ($resolvedTarget === false || $basePath === false || !$this->isWithin($basePath, $resolvedTarget)) {
            throw new \RuntimeException('The destination is outside the project files directory.');
        }

        return $target;
    }

    /**
     * Validates a file name and rejects path traversal or absolute paths.
     *
     * @param string $fileName File name to validate.
     * @return void
     */
    private function validateFileName(string $fileName): void
    {
        if (
            $fileName === ''
            || $fileName === '.'
            || $fileName === '..'
            || str_contains($fileName, "\0")
            || str_contains($fileName, '/')
            || str_contains($fileName, '\\')
            || basename($fileName) !== $fileName
        ) {
            throw new \InvalidArgumentException('Invalid file name.');
        }
    }

    /**
     * Splits and validates a relative directory path.
     *
     * @param string $path Relative directory path.
     * @return list<string> Validated path segments.
     */
    private function relativeSegments(string $path): array
    {
        if ($path === '' || $path === '.') {
            return [];
        }

        if (str_contains($path, "\0") || preg_match('/^[\\\\\/]|^[A-Za-z]:[\\\\\/]/', $path)) {
            throw new \InvalidArgumentException('Invalid files path.');
        }

        $segments = preg_split('/[\\\\\/]+/', trim($path, " \t\n\r\0\x0B\\/"));
        if ($segments === false) {
            throw new \InvalidArgumentException('Invalid files path.');
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Invalid files path.');
            }
        }

        return $segments;
    }

    /**
     * Checks whether a path is inside a base directory.
     *
     * @param string $basePath Canonical base directory.
     * @param string $path Canonical candidate path.
     * @return bool True when the candidate is inside the base directory.
     */
    private function isWithin(string $basePath, string $path): bool
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return $path === rtrim($basePath, DIRECTORY_SEPARATOR)
            || str_starts_with($path, $basePath);
    }

    /**
     * Retrieves an instance of the Luma class.
     * @return Luma Instance of the Luma class.
     */
    public function __debugInfo(): array
    {
        return [
            'Lumynus' => "Framework PHP"
        ];
    }
}
