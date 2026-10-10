<?php

declare(strict_types=1);

namespace Lumynus\Http\Contracts;

interface Response
{
    /**
     * Sets the HTTP response status code.
     *
     * @param int $code HTTP status code (100–599).
     * @return self
     */
    public function status(int $code): self;

    /**
     * Retrieves the current HTTP status code.
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * Sets an HTTP header.
     * Protected against CRLF injection.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function header(string $name, string $value): self;

    /**
     * Returns all set HTTP headers.
     *
     * @return array<string,string>
     */
    public function getHeaders(): array;

    /**
     * Returns the response body.
     *
     * @return string|null
     */
    public function getBody(): ?string;

    /**
     * Returns the file stream resource.
     *
     * @return mixed
     */
    public function getFileStream(): mixed;

    /**
     * Checks whether the response has already been dispatched.
     *
     * @return bool
     */
    public function isSent(): bool;

    /**
     * Returns the length of the response body.
     *
     * @return int
     */
    public function getBodyLength(): int;

    /**
     * Returns the file length.
     *
     * @return int
     */
    public function getFileLength(): int;

    /**
     * Prepares a JSON response.
     *
     * @param mixed $data Data to serialize.
     * @return self
     */
    public function json(mixed $data = null): self;

    /**
     * Prepares an HTML response.
     *
     * @param string|null $html HTML content.
     * @return self
     */
    public function html(?string $html = null): self;

    /**
     * Prepares a plain text response.
     *
     * @param string|null $text Text content.
     * @return self
     */
    public function text(?string $text = null): self;

    /**
     * Prepares a file download or inline stream.
     *
     * @param string $filePath Absolute path to the file.
     * @param bool $download Force download attachment if true.
     * @return self
     */
    public function file(string $filePath, bool $download = false): self;

    /**
     * Prepares an HTTP redirect.
     *
     * @param string $url Target redirect URL.
     * @param int $code Redirect status code (301, 302, etc.).
     * @return self
     */
    public function redirect(string $url, int $code = 302): self;

    /**
     * Prepares a generic response.
     *
     * @param string $text Optional text content.
     * @return self
     */
    public function send(string $text = ''): self;

    /**
     * Dispatches the response (headers and body) to the client.
     *
     * @return void
     */
    public function dispatch(): void;
}
