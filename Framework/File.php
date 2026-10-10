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
            $pathAbsolute = $this->getPath($path);
            if (!file_exists($pathAbsolute . $fileName)) {
                Logs::register('File not found: ' . $fileName, 'error');
                return null;
            }
            return file_get_contents($pathAbsolute . $fileName);
        } catch (\Throwable $th) {
            Logs::register('File read error: ' . $th->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Renames a file in project files directory.
     * @param string $fileName Name of file to rename.
     * @param string $newName New name for the file.
     * @param string $path Relative path inside files directory to rename file in (optional).
     * @return bool Returns true if file was successfully renamed, false otherwise.
     */
    public function replace(string $fileName, string $newName, string $path = ''): bool
    {
        try {
            $pathAbsolute = $this->getPath($path);

            $oldFile = $pathAbsolute . $fileName;

            if (!is_file($oldFile)) {
                Logs::register('File not found: ' . $fileName, 'error');
                return false;
            }

            if (!is_dir($pathAbsolute) && !mkdir($pathAbsolute, 0755, true)) {
                Logs::register('Failed to create directory: ' . $pathAbsolute, 'error');
                return false;
            }
            return rename($pathAbsolute . $fileName, $pathAbsolute . $newName);
        } catch (\Throwable $th) {
            Logs::register('File rename error: ' . $th->getMessage(), 'error');
            return false;
        }
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
            $pathAbsolute = $this->getPath($path);
            return file_exists($pathAbsolute . $fileName);
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
            $pathAbsolute = $this->getPath($path);
            $file = $pathAbsolute . $fileName;

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
     * @return bool Returns true if file was saved successfully, false otherwise.
     */
    public function save(string $from, string $newName, string $path = ''): bool
    {
        try {
            $pathAbsolute = $this->getPath($path);
            if (!is_dir($pathAbsolute) && !mkdir($pathAbsolute, 0755, true)) {
                Logs::register('Failed to create directory: ' . $pathAbsolute, 'error');
                return false;
            }
            return move_uploaded_file($from, $pathAbsolute . $newName);
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
     * @return bool Returns true if file was copied successfully, false otherwise.
     */
    public function copy(string $fileName, string $newName, string $path = ''): bool
    {
        try {
            $pathAbsolute = $this->getPath($path);
            if (!is_dir($pathAbsolute) && !mkdir($pathAbsolute, 0755, true)) {
                Logs::register('Failed to create directory: ' . $pathAbsolute, 'error');
                return false;
            }
            return copy($pathAbsolute . $fileName, $pathAbsolute . $newName);
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
        $pathAbsolute = $this->getPath($path);

        if (!is_dir($pathAbsolute)) {
            return [];
        }

        $files = array_diff(
            scandir($pathAbsolute, SCANDIR_SORT_DESCENDING),
            ['.', '..']
        );

        $key = Sanitizer::string($key);
        if (!empty($key)) {
            $files = array_filter($files, function ($file) use ($key) {
                return str_contains($file, $key);
            });
        }

        return $files;
    }

    /**
     * Gets absolute path to project files directory based on given relative path.
     * @param string $path Relative path inside files directory (optional).
     * @return string Absolute path to files directory.
     */
    public function getPath(string $path = ''): string
    {
        $projectPath = Config::projectPath();
        $pathFiles = Config::getApplicationConfig()['path']['files'];

        return rtrim($projectPath . $pathFiles, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ($path !== ''
                ? trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                : '');
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
