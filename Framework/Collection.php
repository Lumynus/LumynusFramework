<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;
use Lumynus\Framework\Logs;

class Collection extends LumaClasses
{
    public function __construct(private array $items) {}

    /**
     * Checks whether key exists na collection.
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->hasRecursive($this->items, $key);
    }

    /**
     * Checks whether key exists na collection.
     * @param string $key
     * @return bool
     */
    public function exists(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Searches for a key in the collection.
     * @param string $key
     * @param bool $recursive
     * @return array
     */

    public function search(string $key, bool $recursive = false): array
    {
        return $this->searchArray($this->items, $key, $recursive);
    }

    /**
     * Updates a key in the collection.
     * @param string $key
     * @param mixed $newValue
     * @return bool
     */
    public function update(string $key, mixed $newValue): bool
    {
        if (!array_key_exists($key, $this->items)) {
            return false;
        }

        $this->items[$key] = $newValue;
        return true;
    }

    /**
     * Updates all matching keys in the collection.
     * @param string $key
     * @param mixed $newValue
     * @return bool
     */
    public function updateAll(string $key, mixed $newValue): bool
    {
        return $this->updateRecursive($this->items, $key, $newValue);
    }

    /**
     * Removes a key from the collection.
     * @param string $key
     * @return bool
     */
    /**
     * Removes a key from the collection.
     * @param string $key
     * @return bool
     */
    /**
     * Removes a key from the collection.
     * @param string $key
     * @return bool
     */
    public function remove(string $key): bool
    {
        if (!array_key_exists($key, $this->items)) {
            return false;
        }

        unset($this->items[$key]);

        return true;
    }

    /**
     * Removes all matching keys from the collection.
     * @param string $key
     * @return bool
     */
    public function removeAll(string $key): bool
    {
        return $this->removeRecursive($this->items, $key);
    }

    /**
     * Adds a key to the collection.
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set(string $key, mixed $value): bool
    {
        if (array_key_exists($key, $this->items)) {
            Logs::register("Collection", "Key $key already exists in the collection");
            return false;
        }

        $this->items[$key] = $value;

        return true;
    }

    /**
     * Finds a key in the collection.
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $data = $this->searchArray($this->items, $key, true);
        return $data[0] ?? null;
    }

    /**
     * Searches for all matching keys in the collection.
     * @param string $key
     * @return array
     */
    public function getAllByKey(string $key): array
    {
        return $this->searchArray($this->items, $key, true);
    }

    /**
     * Returns all items from the collection.
     * @return array
     */
    public function getAll(): array
    {
        return $this->items;
    }

    /**
     * Returns the number of items in the collection.
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Searches for a key within items array.
     * @param array $items
     * @param string $key
     * @param bool $recursive
     * @return array
     */
    private function searchArray(array $items, string $key, bool $recursive): array
    {
        $located = [];

        foreach ($items as $keyInternal => $value) {
            if ((string)$keyInternal === $key) {
                $located[] = $value;
            }

            if ($recursive && is_array($value)) {
                $located = array_merge(
                    $located,
                    $this->searchArray($value, $key, true)
                );
            }
        }

        return $located;
    }

    /**
     * Updates a key within items array.
     * @param array $items
     * @param string $key
     * @param mixed $newValue
     * @return bool
     */
    private function updateRecursive(array &$items, string $key, mixed $newValue): bool
    {
        $updated = false;

        foreach ($items as $currentKey => &$value) {

            if ((string)$currentKey === $key) {
                $value = $newValue;
                $updated = true;
            }

            if (is_array($value)) {
                $updated = $this->updateRecursive($value, $key, $newValue) || $updated;
            }
        }

        return $updated;
    }

    /**
     * Removes a key within items array.
     * @param array $items
     * @param string $key
     * @return bool
     */
    private function removeRecursive(array &$items, string $key): bool
    {
        $removed = false;

        foreach ($items as $currentKey => &$value) {

            if ((string)$currentKey === $key) {
                unset($items[$currentKey]);
                $removed = true;
                continue;
            }

            if (is_array($value)) {
                $removed = $this->removeRecursive($value, $key) || $removed;
            }
        }

        return $removed;
    }

    /**
     * Checks whether a key exists in items array.
     * @param array $items
     * @param string $key
     * @return bool
     */
    private function hasRecursive(array $items, string $key): bool
    {
        foreach ($items as $currentKey => $value) {

            if ((string)$currentKey === $key) {
                return true;
            }

            if (is_array($value) && $this->hasRecursive($value, $key)) {
                return true;
            }
        }

        return false;
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
