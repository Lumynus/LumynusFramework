<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;
use Lumynus\Framework\Config;
use Lumynus\Framework\Logs;

/**
 * Class QueueManager
 *
 * Manages NDJSON queues with support for insert, update, remove, dequeue and read operations.
 * Each queue is stored in NDJSON files in the specified directory.
 * Has locking mechanism to avoid concurrency issues.
 */
final class QueueManager extends LumaClasses
{
    private string $queueDir = '';


    /**
     * Constructor. Initializes queue directory based on project configuration.
     * 
     * @param bool $debug Enables debug logging
     * @throws \RuntimeException If queue directory cannot be created
     */
    public function __construct()
    {

        $this->queueDir = Config::projectPath()
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'queues'
            . DIRECTORY_SEPARATOR;

        if (!is_dir($this->queueDir) && !mkdir($this->queueDir, 0755, true)) {
            throw new \RuntimeException("Failed to create queue directory: {$this->queueDir}");
        }
    }

    /**
     * Returns full path of the NDJSON file.
     * 
     * @param string $file File name
     * @return string Full path to the NDJSON file
     */
    private function getFilePath(string $file): string
    {
        $filename = basename($file);
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filename);

        if (empty($filename)) {
            throw new \InvalidArgumentException("Invalid queue file name.");
        }

        return $this->queueDir . $filename . '.ndjson';
    }


    /**
     * Returns full path to the lock file.
     * 
     * @param string $file NDJSON file path
     * @return string Lock file path
     */
    private function getLockPath(string $file): string
    {
        return $file . '.lock';
    }

    /**
     * Insere um novo item no final da fila NDJSON.
     * 
     * @param array $data Item a ser inserido (array associativo)
     * @param string $file File name da fila
     * @return bool True on success, false on error or locked queue
     */
    public function insert(array $data, string $file): bool
    {
        if (empty($data)) {
            $this->log("Empty data provided");
            return false;
        }

        $filePath = $this->getFilePath($file);

        try {
            $jsonLine = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonLine === false) {
                $this->log("JSON encode failed: " . json_last_error_msg());
                return false;
            }

            $fp = fopen($filePath, 'a+');
            if (!$fp) {
                $this->log("File open failed: {$filePath}");
                return false;
            }

            if (flock($fp, LOCK_EX)) {
                $bytes = fwrite($fp, $jsonLine . PHP_EOL);
                fflush($fp);
                flock($fp, LOCK_UN);
            } else {
                $this->log("Could not acquire flock: {$filePath}");
                fclose($fp);
                return false;
            }

            fclose($fp);

            if ($bytes === false) {
                $this->log("File write failed: {$filePath}");
                return false;
            }

            $this->log("Insert successful, bytes written: {$bytes}");
            return true;
        } catch (\Throwable $e) {
            $this->log("Insert exception: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Removes queue lines containing a key with specified value.
     * 
     * @param string $file File name NDJSON
     * @param string $key Key to verify
     * @param mixed $value Matching value for deletion
     * @return bool True on success, false on error or locked queue
     */
    public function remove(string $file, string $key, $value): bool
    {
        $filePath = $this->getFilePath($file);

        if (!file_exists($filePath)) {
            $this->log("File does not exist: {$filePath}");
            return false;
        }

        $fp = fopen($filePath, 'c+');
        if (!$fp) {
            $this->log("Failed to open file: {$filePath}");
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            $this->log("Could not acquire flock: {$filePath}");
            fclose($fp);
            return false;
        }

        try {
            $lines = [];
            while (($line = fgets($fp)) !== false) {
                $line = trim($line);
                if ($line !== '') $lines[] = $line;
            }

            if (empty($lines)) return false;

            $newLines = [];
            foreach ($lines as $line) {
                $decoded = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE) continue;
                if (!is_array($decoded) || !isset($decoded[$key]) || $decoded[$key] !== $value) {
                    $newLines[] = $line;
                }
            }

            ftruncate($fp, 0);
            rewind($fp);
            if (!empty($newLines)) {
                fwrite($fp, implode(PHP_EOL, $newLines) . PHP_EOL);
            }
        } catch (\Throwable $e) {
            $this->log("Remove exception: " . $e->getMessage());
            return false;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return true;
    }

    /**
     * Updates queue rows matching key-value, merging with new data.
     * 
     * @param string $file File name NDJSON
     * @param string $key Key to verify
     * @param mixed $value Key value
     * @param array $newData New data to merge
     * @return bool True on success, false on error or locked queue
     */
    public function update(string $file, string $key, $value, array $newData): bool
    {
        $filePath = $this->getFilePath($file);

        if (!file_exists($filePath)) return false;

        $fp = fopen($filePath, 'c+');
        if (!$fp) {
            $this->log("Failed to open file: {$filePath}");
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            $this->log("Could not acquire flock: {$filePath}");
            fclose($fp);
            return false;
        }

        try {

            $lines = [];
            while (($line = fgets($fp)) !== false) {
                $line = trim($line);
                if ($line !== '') $lines[] = $line;
            }

            if (empty($lines)) return false;

            $updatedLines = [];
            foreach ($lines as $line) {
                $decoded = json_decode($line, true);
                if (is_array($decoded) && isset($decoded[$key]) && $decoded[$key] === $value) {
                    $decoded = array_merge($decoded, $newData);
                    $line = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if (json_last_error() !== JSON_ERROR_NONE) continue;
                }
                $updatedLines[] = $line;
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, implode(PHP_EOL, $updatedLines) . PHP_EOL);
        } catch (\Throwable $e) {
            $this->log("Update exception: " . $e->getMessage());
            return false;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return true;
    }


    /**
     * Deletes an NDJSON queue file and its corresponding lock file.
     * 
     * @param string $file Queue file name
     * @return bool True on success, false on error
     */
    public function delete(string $file): bool
    {
        $filePath = $this->getFilePath($file);
        $lockFile = $this->getLockPath($filePath);

        $success = true;

        if (file_exists($lockFile)) {
            if (!@unlink($lockFile)) {
                $this->log("Failed to delete lock file: {$lockFile}");
                $success = false;
            } else {
                $this->log("Lock file deleted: {$lockFile}");
            }
        }

        if (file_exists($filePath)) {
            if (!@unlink($filePath)) {
                $this->log("Failed to delete queue file: {$filePath}");
                return false;
            } else {
                $this->log("Queue file deleted: {$filePath}");
            }
        } else {
            $this->log("Queue file does not exist: {$filePath}");
            return false;
        }

        return $success;
    }

    /**
     * Returns all queue items as associative array with lock safety.
     * * @param string $file File name NDJSON
     * @return array Array of arrays
     */
    public function getAsArray(string $file): array
    {
        $filePath = $this->getFilePath($file);

        if (!file_exists($filePath)) return [];

        $fp = fopen($filePath, 'r');

        if (!$fp) {
            $this->log("Failed to open file for reading: {$filePath}");
            return [];
        }

        $items = [];

        if (flock($fp, LOCK_SH)) {

            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);

            if ($content) {
                $lines = explode(PHP_EOL, $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;

                    $decoded = json_decode($line, true);
                    if (json_last_error() !== JSON_ERROR_NONE) continue;
                    if (is_array($decoded)) {
                        $items[] = $decoded;
                    }
                }
            }
        } else {
            $this->log("Could not acquire shared lock for reading: {$filePath}");
        }
        fclose($fp);
        return $items;
    }

    /**
     * Returns all queue items as stdClass objects.
     * 
     * @param string $file File name NDJSON
     * @return array Array of objects
     */
    public function getAsObjects(string $file): array
    {
        $filePath = $this->getFilePath($file);

        if (!file_exists($filePath)) return [];

        $fp = fopen($filePath, 'r');

        if (!$fp) {
            $this->log("Failed to open file for reading: {$filePath}");
            return [];
        }

        $items = [];

        if (flock($fp, LOCK_SH)) {

            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);

            if ($content) {
                $lines = explode(PHP_EOL, $content);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;

                    $decoded = json_decode($line);
                    if (json_last_error() !== JSON_ERROR_NONE) continue;
                    if (is_object($decoded)) {
                        $items[] = $decoded;
                    }
                }
            }
        } else {
            $this->log("Could not acquire shared lock for reading: {$filePath}");
        }
        fclose($fp);
        return $items;
    }

    /**
     * Pops items from the queue
     * 
     * @param string $file File name da fila
     * @param int|null $limit Maximum items to pop (null = all)
     * @return array|null Array of popped items or null if empty/error
     */
    public function dequeue(string $file, ?int $limit = null): ?array
    {
        $filePath = $this->getFilePath($file);

        if (!file_exists($filePath)) return null;

        $itemsRemoved = null;


        $fp = fopen($filePath, 'c+');
        if (!$fp) {
            $this->log("Failed to open file: {$filePath}");
            return null;
        }


        if (!flock($fp, LOCK_EX)) {
            $this->log("Could not acquire flock: {$filePath}");
            fclose($fp);
            return null;
        }

        try {

            $lines = [];
            while (($line = fgets($fp)) !== false) {
                $line = trim($line);
                if ($line !== '') $lines[] = $line;
            }

            if (empty($lines)) return null;

            $limit = ($limit === null || $limit > $this->l_count($lines)) ? $this->l_count($lines) : $limit;

            $linesToProcess = array_slice($lines, 0, $limit);
            $linesLeftover = array_slice($lines, $limit);

            $itemsRemoved = [];
            foreach ($linesToProcess as $line) {
                $decoded = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }
                if (is_array($decoded)) $itemsRemoved[] = $decoded;
            }

            ftruncate($fp, 0);
            rewind($fp);
            if (!empty($linesLeftover)) {
                fwrite($fp, implode(PHP_EOL, $linesLeftover) . PHP_EOL);
            }
        } catch (\Throwable $e) {
            $this->log("Dequeue exception: " . $e->getMessage());
            return null;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return empty($itemsRemoved) ? null : $itemsRemoved;
    }


    /**
     * Pops and returns a single item from the queue (FIFO)
     * 
     * @param string $file File name da fila
     * @return array|null Popped item or null if empty/error
     */
    public function dequeueOne(string $file): ?array
    {
        $result = $this->dequeue($file, 1);
        return $result ? $result[0] : null;
    }

    /**
     * Logs debug and error tracking messages.
     * 
     * @param string $message Message to log
     * @param string $level Log level (debug, error)
     * @return void
     */
    private function log(string $message): void
    {
        Logs::register('QueueManager', $message);
    }

    /**
     * Returns information about the queues directory.
     * 
     * @return array Array containing directory existence, write permissions, and file list
     */
    public function getQueueInfo(): array
    {
        return [
            'queueDir' => $this->queueDir,
            'exists' => is_dir($this->queueDir),
            'writable' => is_writable($this->queueDir),
            'files' => is_dir($this->queueDir) ? glob($this->queueDir . '*.ndjson') : []
        ];
    }
}
