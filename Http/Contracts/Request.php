<?php

declare(strict_types=1);

namespace Lumynus\Http\Contracts;

interface Request
{
    /**
     * Returns the HTTP request method.
     */
    public function getMethod(): string;

    /**
     * Returns the requested URI.
     */
    public function getUri(): string;

    /**
     * Returns all query string parameters.
     *
     * @return array<string, mixed>
     */
    public function getQueryParams(): array;

    /**
     * Returns the parsed body content.
     *
     * @return array<string, mixed>|null
     */
    public function getParsedBody(): array|null;

    /**
     * Returns parsed request body data.
     *
     * @param string|null $key     Key to retrieve or null for all data.
     * @param mixed       $default Default value if the key does not exist.
     */
    public function body(string|null $key, mixed $default = null): mixed;

    /**
     * Returns all request headers.
     *
     * @return array<string, mixed>
     */
    public function getHeaders(): array;

    /**
     * Returns a specific request header.
     *
     * @param string $key Header name.
     * @param mixed  $default Default value if header is absent.
     */
    public function getHeader(string $key, mixed $default = null): mixed;

    /**
     * Returns a specific query string value.
     *
     * @param string $key Query parameter key.
     * @param mixed  $default Default value if key is absent.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Returns a single uploaded file.
     *
     * @param string $key File input name.
     * @param mixed  $default Default value if file is absent.
     */
    public function file(string $key, mixed $default = null): mixed;

    /**
     * Returns all uploaded files.
     *
     * @return array
     */
    public function files(): array;

    /**
     * Returns server environment variables.
     *
     * @return mixed
     */
    public function server(): mixed;

    /**
     * Sets or updates an internal request attribute.
     *
     * @param string $key Attribute key.
     * @param mixed $value Attribute value.
     */
    public function setAttribute(string $key, mixed $value): void;

    /**
     * Removes an internal request attribute.
     *
     * @param string $key Attribute key.
     */
    public function unsetAttribute(string $key): void;

    /**
     * Retrieves an internal request attribute.
     *
     * @param string $key Attribute key.
     * @param mixed $default Default value if attribute is absent.
     */
    public function getAttribute(string $key, mixed $default = null): mixed;

    /**
     * Returns all internal request attributes.
     *
     * @return array
     */
    public function getAttributes(): array;
}
