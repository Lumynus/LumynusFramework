<?php

declare(strict_types=1);

namespace Lumynus\Http\Errors;

use Lumynus\Contracts\ErrorFormatter;

/**
 * Formats errors using the default Lumynus format.
 *
 * @package Lumynus\Http\Errors
 */
final class DefaultErrorFormatter implements ErrorFormatter
{
    /**
     * {@inheritDoc}
     */
    public function body(ErrorPayload $erro): array
    {
        return [
            'error' => $erro->message,
            'code' => $erro->status,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function text(ErrorPayload $erro): ?string
    {
        return is_array($erro->message)
            ? print_r($erro->message, true)
            : $erro->message;
    }

    /**
     * {@inheritDoc}
     */
    public function html(ErrorPayload $erro): ?string
    {
        return null;
    }
}
