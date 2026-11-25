<?php

declare(strict_types=1);

namespace Lumynus\Http\Errors;

use Lumynus\Http\HttpResponse;

/**
 * Provides compatible error response and termination behavior.
 *
 * @package Lumynus\Http\Errors
 */
trait RespondsWithError
{
    /**
     * Builds an HTTP error response without dispatching or terminating.
     *
     * @param string|array<int|string, mixed>|null $message Error message.
     * @param int $code HTTP status.
     * @param string|null $forceType Forced format.
     * @return HttpResponse Prepared error response.
     */
    public static function errorResponse(
        string|array|null $message = null,
        int $code = 500,
        ?string $forceType = null
    ): HttpResponse {
        return ErrorResponder::fromConfig()
            ->respond(
                new ErrorPayload(
                    $code,
                    $message ?? 'Occurred an error in application'
                ),
                $forceType
            );
    }

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
        self::errorResponse($message, $code, $forceType)->dispatch();

        exit;
    }
}
