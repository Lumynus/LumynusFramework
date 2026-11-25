<?php

declare(strict_types=1);

namespace Lumynus\Http\Errors;

/**
 * Represents normalized data for an error response.
 *
 * @package Lumynus\Http\Errors
 */
final readonly class ErrorPayload
{
    /**
     * Creates normalized error data.
     *
     * @param int $status HTTP status.
     * @param string|array<int|string, mixed> $message Public error message.
     * @param array<string, mixed> $debug Technical details used in debug mode.
     */
    public function __construct(
        public int $status,
        public string|array $message,
        public array $debug = [],
    ) {}

    /**
     * Creates a payload from a thrown exception or error.
     *
     * @param \Throwable $error Thrown exception.
     * @param int $status HTTP status.
     * @return self Normalized payload.
     */
    public static function fromThrowable(\Throwable $error, int $status = 500): self
    {
        return new self($status, $error->getMessage(), [
            'error_message' => $error->getMessage(),
            'error_type' => get_class($error),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'error_code' => $status,
        ]);
    }

    /**
     * Creates a payload from a PHP error.
     *
     * @param int $severity Error severity.
     * @param string $message Error message.
     * @param string $file File where the error occurred.
     * @param int $line Error line.
     * @return self Normalized payload.
     */
    public static function fromPhpError(int $severity, string $message, string $file, int $line): self
    {
        return new self(500, $message, [
            'error_message' => $message,
            'error' => $severity,
            'file' => $file,
            'line' => $line,
            'error_code' => 500,
        ]);
    }

    /**
     * Creates a payload from fatal error information.
     *
     * @param array<string, mixed> $error Data returned by error_get_last().
     * @return self Normalized payload.
     */
    public static function fromFatal(array $error): self
    {
        return new self(500, (string) ($error['message'] ?? 'Internal Server Error'), [
            'error_message' => (string) ($error['message'] ?? 'Internal Server Error'),
            'error' => 'Fatal error caught',
            'file' => (string) ($error['file'] ?? ''),
            'line' => (int) ($error['line'] ?? 0),
            'error_code' => 500,
        ]);
    }
}
