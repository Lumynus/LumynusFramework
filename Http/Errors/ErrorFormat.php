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

        $accept = trim((string) ($server['HTTP_ACCEPT'] ?? ''));
        if ($accept !== '') {
            return self::fromAccept($accept);
        }

        return self::fromContentType((string) ($server['CONTENT_TYPE'] ?? ''));
    }

    /**
     * Selects the best supported format from an Accept header.
     *
     * @param string $accept Accept header value.
     * @return self Selected format.
     */
    private static function fromAccept(string $accept): self
    {
        $matches = [];

        foreach (explode(',', $accept) as $position => $part) {
            $parameters = array_map('trim', explode(';', strtolower(trim($part))));
            $mediaType = array_shift($parameters);
            if ($mediaType === null || $mediaType === '') {
                continue;
            }

            $quality = 1.0;
            foreach ($parameters as $parameter) {
                [$name, $value] = array_pad(explode('=', $parameter, 2), 2, null);
                if ($name !== 'q') {
                    continue;
                }
                if ($value === null || !is_numeric($value)) {
                    $quality = 0.0;
                    break;
                }
                $quality = (float) $value;
                if ($quality < 0.0 || $quality > 1.0) {
                    $quality = 0.0;
                    break;
                }
            }

            if ($quality <= 0.0) {
                continue;
            }

            $format = self::fromMediaType($mediaType);
            if ($format !== null) {
                $matches[] = ['format' => $format, 'quality' => $quality, 'position' => $position];
            }
        }

        usort(
            $matches,
            static fn (array $left, array $right): int =>
                ($right['quality'] <=> $left['quality']) ?: ($left['position'] <=> $right['position'])
        );

        return $matches[0]['format'] ?? self::Html;
    }

    /**
     * Maps a media type to a supported error format.
     *
     * @param string $mediaType Media type.
     * @return self|null Supported format or null.
     */
    private static function fromMediaType(string $mediaType): ?self
    {
        return match (true) {
            $mediaType === 'text/html',
            $mediaType === 'application/xhtml+xml' => self::Html,
            $mediaType === 'application/json',
            str_ends_with($mediaType, '+json') => self::Json,
            $mediaType === 'application/xml',
            $mediaType === 'text/xml' => self::Xml,
            $mediaType === 'application/javascript',
            $mediaType === 'text/javascript' => self::JavaScript,
            $mediaType === 'text/plain' => self::Text,
            default => null,
        };
    }

    /**
     * Selects a format from Content-Type when Accept is absent.
     *
     * @param string $contentType Content-Type header value.
     * @return self Selected format.
     */
    private static function fromContentType(string $contentType): self
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
        return self::fromMediaType($mediaType) ?? self::Html;
    }
}
