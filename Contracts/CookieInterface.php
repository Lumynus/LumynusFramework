<?php

declare(strict_types=1);

namespace Lumynus\Contracts;

/**
 * Interface defining secure cookie operations.
 */
interface CookieInterface
{
    /**
     * Sets a cookie.
     *
     * @param string $key Cookie key name.
     * @param mixed $value Cookie value.
     * @param int $expire Expiration time in seconds (0 for session cookie).
     * @param array $options Additional options for the cookie.
     * @return void
     */
    public function set(string $key, mixed $value, int $expire = 0, array $options = []): void;

    /**
     * Retrieves the value of a cookie.
     *
     * @param string $key Cookie key name.
     * @return mixed Cookie value.
     */
    public function get(string $key): mixed;

    /**
     * Checks whether a cookie exists and is valid.
     *
     * @param string $key Cookie key name.
     * @return bool True if the cookie exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Removes a cookie.
     *
     * @param string $key Cookie key name.
     * @param array $options Additional options for the cookie.
     * @return void
     */
    public function remove(string $key, array $options = []): void;

    /**
     * Removes all managed cookies.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Regenerates the cookies keeping values intact.
     *
     * @return void
     */
    public function regenerate(): void;

    /**
     * Gets a unique identifier for the current cookie set.
     *
     * @return string Cookie set identifier.
     */
    public function getId(): string;

    /**
     * Retrieves all valid cookies.
     *
     * @return array Associative array containing all valid cookies.
     */
    public function getAll(): array;
}
