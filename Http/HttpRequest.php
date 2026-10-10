<?php

declare(strict_types=1);

namespace Lumynus\Http;

use Lumynus\Http\Contracts\Request as RequestInterface;

final class HttpRequest implements RequestInterface
{
    private array $attributes = [];
    private bool $bodyParsed = false;

    /**
     * Constructor
     */
    public function __construct(
        private string $method,
        private string $uri,
        private array $query,
        private array $post,
        private array $headers,
        private array $files,
        private array $server,
        private mixed $body
    ) {
        $this->headers = $this->normalizeHeaders($headers);
    }

    /**
     * Creates a Request instance from PHP global variables,
     * centralizing access to incoming HTTP request data.
     */
    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Supports HTTP Method Spoofing
        if ($method === 'POST') {
            $override = $_POST['_method'] 
                ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] 
                ?? null;

            if ($override && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = strtoupper($override);
            }
        }

        return new self(
            $method,
            $_SERVER['REQUEST_URI'] ?? '/',
            $_GET,
            $_POST,
            self::headersFromGlobals(),
            $_FILES,
            $_SERVER,
            null
        );
    }

    /**
     * Extracts HTTP headers from the $_SERVER superglobal,
     * normalizing names to standard header casing.
     */
    private static function headersFromGlobals(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (
                str_starts_with($key, 'HTTP_') ||
                in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)
            ) {
                $name = strtolower(
                    str_replace('_', '-', str_replace('HTTP_', '', $key))
                );

                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Normalizes header names to lowercase with hyphens.
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $key = strtolower(str_replace('_', '-', $name));
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Parses the request body according to Content-Type.
     */
    private function parseBody(): void
    {
        if ($this->bodyParsed) {
            return;
        }

        $this->bodyParsed = true;

        $contentType = strtolower($this->headers['content-type'] ?? '');
        $method = $this->method;

        if ($method === 'GET') {
            $this->body = null;
            return;
        }

        if (
            !empty($this->post) &&
            str_contains($contentType, 'application/x-www-form-urlencoded')
        ) {
            $this->body = $this->post;
            return;
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            $this->body = $this->post ?: null;
            return;
        }

        $raw = file_get_contents('php://input');

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            $this->body = is_array($decoded) ? $decoded : null;
            return;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($raw, $parsed);
            $this->body = $parsed ?: null;
            return;
        }

        if (str_contains($contentType, 'text/plain')) {
            $this->body = $raw !== '' ? ['_raw' => $raw] : null;
            return;
        }

        if (
            str_contains($contentType, 'application/xml') ||
            str_contains($contentType, 'text/xml')
        ) {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $this->body = $xml ? json_decode(json_encode($xml), true) : null;
            return;
        }

        if (str_contains($contentType, 'application/graphql')) {
            $this->body = ['query' => $raw];
            return;
        }

        if (str_contains($contentType, 'application/octet-stream')) {
            $this->body = ['_stream' => $raw];
            return;
        }

        if ($raw === '' || $raw === false) {
            $this->body = !empty($this->post) ? $this->post : null;
            return;
        }

        $this->body = !empty($this->post) ? $this->post : ['_raw' => $raw];
    }

    /**
     * Returns the HTTP request method (GET, POST, PUT, DELETE, etc.).
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Returns the requested URI path and query.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Returns all parameters sent via query string (GET).
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    /**
     * Returns the parsed body content.
     */
    public function getParsedBody(): array|null
    {
        $this->parseBody();
        return $this->body;
    }

    /**
     * Returns data from the HTTP request body.
     *
     * Supports JSON, x-www-form-urlencoded and multipart/form-data
     * across POST, PUT, PATCH, DELETE methods.
     *
     * @param string|null $key     Key to retrieve or null for all body data.
     * @param mixed       $default Default value if key is not found.
     *
     * @return mixed Array of all data, specific value, or default/null.
     */
    public function body(string|null $key = null, mixed $default = null): mixed
    {
        $data = $this->getParsedBody();

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Returns all HTTP request headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Returns a specific HTTP request header.
     */
    public function getHeader(string $key, mixed $default = null): mixed
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    /**
     * Retrieves a specific value from the query string (GET).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Returns a specific uploaded file from $_FILES.
     */
    public function file(string $key, mixed $default = null): mixed
    {
        return $this->files[$key] ?? $default;
    }

    /**
     * Returns all uploaded files.
     */
    public function files(): array
    {
        return $this->files ?? [];
    }

    /**
     * Returns the server environment parameters.
     */
    public function server(): mixed
    {
        return $this->server ?? [];
    }

    /**
     * Adds or updates an internal request attribute (used by middlewares).
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Removes an internal request attribute.
     */
    public function unsetAttribute(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Retrieves an internal request attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Returns all internal request attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
