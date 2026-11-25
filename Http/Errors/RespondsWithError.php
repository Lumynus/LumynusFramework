<?php

declare(strict_types=1);

namespace Lumynus\Http\Errors;

/**
 * Provides compatible error response and termination behavior.
 *
 * @package Lumynus\Http\Errors
 */
trait RespondsWithError
{
    /**
     * Responds with an HTTP error and terminates the request.
     *
     * @param string|array<int|string, mixed>|null $message Error message.
     * @param int $code HTTP status.
     * @param string|null $forceType Forced format.
     * @return never
     */
    public static function abortWithError(
        string|array|null $message = null,
        int $code = 500,
        ?string $forceType = null
    ): never {
        ErrorResponder::fromConfig()
            ->respond(
                new ErrorPayload(
                    $code,
                    $message ?? 'Occurred an error in application'
                ),
                $forceType
            )
            ->dispatch();

        exit;
    }
}
