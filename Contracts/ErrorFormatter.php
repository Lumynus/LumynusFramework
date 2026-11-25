<?php

declare(strict_types=1);

namespace Lumynus\Contracts;

use Lumynus\Http\Errors\ErrorPayload;

/**
 * Contract for formatting custom error response bodies.
 *
 * @package Lumynus\Contracts
 */
interface ErrorFormatter
{
    /**
     * Builds the structured data used by JSON, XML, JavaScript, and text formats.
     *
     * @param ErrorPayload $erro Normalized error data.
     * @return array<string, mixed> Structured error data.
     */
    public function body(ErrorPayload $erro): array;

    /**
     * Builds custom HTML or returns null to use the framework default.
     *
     * @param ErrorPayload $erro Normalized error data.
     * @return string|null Custom HTML or null.
     */
    public function html(ErrorPayload $erro): ?string;
}
