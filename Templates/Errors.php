<?php

declare(strict_types=1);

namespace Lumynus\Templates;

use Lumynus\Http\Errors\RespondsWithError;

/**
 * Compatibility bridge for framework error responses.
 *
 * @package Lumynus\Templates
 * @deprecated 1.10 Use Lumynus\Http\Errors\RespondsWithError::abortWithError(). Removido na 2.0.
 */
trait Errors
{
    use RespondsWithError;

    /**
     * Keeps the legacy method while delegating behavior to the central responder.
     *
     * @param string|array<int|string, mixed>|null $message Error message.
     * @param int $code HTTP status code.
     * @param string|null $forceType Forced format.
     * @return never
     * @deprecated 1.10 Use abortWithError().
     */
    public static function throwError(string|array|null $message = null, int $code = 500, ?string $forceType = null): never
    {
        self::abortWithError($message, $code, $forceType);
    }
}
