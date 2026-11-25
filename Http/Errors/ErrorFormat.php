<?php

declare(strict_types=1);

namespace Lumynus\Http\Errors;

/**
 * Formats supported by framework error responses.
 *
 * @package Lumynus\Http\Errors
 */
enum ErrorFormat: string
{
    case Json = 'json';
    case Xml = 'xml';
    case JavaScript = 'javascript';
    case Text = 'plain';
    case Html = 'html';

    /**
     * Negotiates the format requested by the client or uses the forced format.
     *
     * @param array<string, mixed> $server Request server data.
     * @param string|null $forced Format explicitly provided by the caller.
     * @return self Selected format.
     */
    public static function negotiate(array $server, ?string $forced = null): self
    {
        if ($forced !== null) {
            return self::tryFrom(strtolower($forced)) ?? self::Html;
        }

        $accept = (string) ($server['HTTP_ACCEPT'] ?? '*/*');
        $contentType = (string) ($server['CONTENT_TYPE'] ?? '');

        if (stripos($accept, 'application/json') !== false || stripos($contentType, 'application/json') !== false) {
            return self::Json;
        }

        if (
            stripos($accept, 'application/xml') !== false ||
            stripos($contentType, 'application/xml') !== false ||
            stripos($accept, 'text/xml') !== false
        ) {
            return self::Xml;
        }

        if (
            stripos($accept, 'application/javascript') !== false ||
            stripos($accept, 'text/javascript') !== false ||
            stripos($contentType, 'application/javascript') !== false
        ) {
            return self::JavaScript;
        }

        if (stripos($accept, 'text/plain') !== false || stripos($contentType, 'text/plain') !== false) {
            return self::Text;
        }

        return self::Html;
    }
}
