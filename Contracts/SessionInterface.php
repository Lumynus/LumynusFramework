<?php

declare(strict_types=1);

namespace Lumynus\Contracts;

/**
 * Interface defining secure session operations.
 */
interface SessionInterface
{
    /**
     * Sets a key and value in the session.
     *
     * @param string $key Session key name.
     * @param mixed $value Session value.
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Retrieves the value of a key from the session.
     *
     * @param string $key Session key name.
     * @return mixed Session value.
     */
    public function get(string $key): mixed;

    /**
     * Checks whether a key exists and is valid in the session.
     *
     * @param string $key Session key name.
     * @return bool True if the key exists and is valid, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Removes a key from the session.
     *
     * @param string $key Session key name.
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Clears all session data.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Regenerates the session ID securely.
     *
     * @return void
     */
    public function regenerate(): void;

    /**
     * Retrieves the current session ID.
     *
     * @return string Current session ID.
     */
    public function getId(): string;

    /**
     * Retrieves all session data.
     *
     * @return array Associative array with all session data.
     */
    public function getAll(): array;
}
