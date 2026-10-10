<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;

final class Converter extends LumaClasses
{

    /**
     * Converts a string to an integer.
     *
     * @param string $input String to convert.
     * @return int|null Converted integer, or null if input is null.
     */
    public static function toInt(string|null $input): int|null
    {
        if ($input === null) {
            return null;
        }
        return (int) $input;
    }

    /**
     * Converts a string to a float.
     *
     * @param string $input String to convert.
     * @return float|null Converted float, or null if input is null.
     */
    public static function toFloat(string|null $input): float|null
    {
        if ($input === null) {
            return null;
        }
        return (float) $input;
    }

    /**
     * Converts a value to a string.
     *
     * @param int|float|string|bool|null $input Value to convert.
     * @return string|null Converted string, or null if input is null.
     */
    public static function toString(int|float|string|bool|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return (string) $input;
    }

    /**
     * Converts a value to a boolean.
     *
     * @param string|int|null $input Value to convert.
     * @return bool|null Converted boolean, or null if input is null.
     */
    public static function toBoolean(string|int|null $input): bool|null
    {
        if ($input === null) {
            return null;
        }
        return filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * Converts an object or array to an associative array recursively.
     *
     * @param object|array|null $input Input object or array.
     * @return array|null Resulting array, or null if input is null.
     */
    public static function toArray(object|array|null $input): array|null
    {
        if ($input === null) {
            return null;
        }
        return json_decode(json_encode($input), true);
    }

    /**
     * Converts an array or object to an object (stdClass) recursively.
     *
     * @param array|object|null $input Input array or object.
     * @return object|null Resulting object, or null if input is null.
     */
    public static function toObject(array|object|null $input): object|null
    {
        if ($input === null) {
            return null;
        }
        return json_decode(json_encode($input));
    }

    /**
     * Converts JSON string into an associative array.
     *
     * @param string|null $json JSON string to convert.
     * @return array|null Resulting array, or null if input is null.
     * @throws \InvalidArgumentException If JSON is invalid.
     */
    public static function jsonToArray(string|null $json): array|null
    {
        if ($json === null) {
            return null;
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Converts JSON string into an object (stdClass).
     *
     * @param string|null $json JSON string to convert.
     * @return object|null Resulting object, or null if input is null.
     * @throws \InvalidArgumentException If JSON is invalid.
     */
    public static function jsonToObject(string|null $json): object|null
    {
        if ($json === null) {
            return null;
        }
        $data = json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Converts a datetime string to a date string formatted as 'Y-m-d'.
     *
     * @param string|null $dateTimeString Datetime string to convert.
     * @return string|null Date string in 'Y-m-d' format, or null if input is null.
     */
    public static function dateTimeToDate(string|null $dateTimeString): string|null
    {
        if ($dateTimeString === null) {
            return null;
        }

        $dateTime = new \DateTime($dateTimeString);
        if (!$dateTime) {
            throw new \InvalidArgumentException("Invalid data: {$dateTimeString}");
        }
        return $dateTime->format('Y-m-d');
    }

    /**
     * Converts a date string to a Unix timestamp (seconds since Unix epoch).
     *
     * @param string|null $dateString Date string to convert.
     * @return int|null Corresponding timestamp, or null if input is null.
     */
    public static function dateToSeconds(string|null $dateString): int|null
    {
        if ($dateString === null) {
            return null;
        }
        $dateTime = new \DateTime($dateString);
        if (!$dateTime) {
            throw new \InvalidArgumentException("Invalid data: {$dateString}");
        }
        return $dateTime->getTimestamp();
    }

    /**
     * Converts seconds into a formatted date string.
     *
     * @param int|null $seconds Number of seconds to convert.
     * @param string $format Date format (default: 'Y-m-d H:i:s').
     * @return string|null Formatted date string, or null if timestamp is negative.
     */
    public static function secondsToDate(int|null $seconds, string $format = 'Y-m-d H:i:s'): string|null
    {
        if ($seconds === null) {
            return null;
        }

        if ($seconds < 0) {
            throw new \InvalidArgumentException("Invalid seconds: {$seconds}");
        }
        return date($format, $seconds);
    }

    /**
     * Normalizes a date string to standard ISO format (Y-m-d).
     * Useful for converting dates received from forms/inputs
     * (e.g., 12/31/2024) to the standard database storage format.
     *
     * @param string|null $dateString Date string to convert.
     * @param string $format Expected input format (default: 'm/d/Y').
     * @return string|null Date formatted as 'Y-m-d', or null if input is null.
     * @throws \InvalidArgumentException If provided date does not match specified format.
     */
    public static function normalizeDate(string|null $dateString, string $format = "m/d/Y"): string|null
    {
        if ($dateString === null) {
            return null;
        }
        $date = \DateTime::createFromFormat($format, $dateString);
        if ($date === false) {
            throw new \InvalidArgumentException("Invalid data: {$dateString}");
        }
        return $date->format('Y-m-d');
    }

    /**
     * Converts datetime string to Unix timestamp considering timezone.
     *
     * @param string $dateString Datetime string to convert.
     * @param \DateTimeZone|null $timezone Timezone to consider (optional).
     * @return int|null Corresponding timestamp, or null if input is null.
     */
    public static function dateToSecondsWithTimezone(string|null $dateString, ?\DateTimeZone $timezone = null): int|null
    {
        if ($dateString === null) {
            return null;
        }
        $dateTime = new \DateTime($dateString, $timezone ?: new \DateTimeZone(date_default_timezone_get()));
        return $dateTime->getTimestamp();
    }

    /**
     * Formats a monetary value to Brazilian currency format (R$).
     *
     * @param float|null $value Value to format.
     * @param string $currency Currency code (default: 'BRL').
     * @param string $locale Locale to use (default: 'pt_BR').
     * @return string|null Formatted currency value, or null if value is null.
     */
    public static function formatCurrency(float|null $value, string $currency = 'BRL', string $locale = 'pt_BR'): string|null
    {
        if ($value === null) {
            return null;
        }
        $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        return $fmt->formatCurrency($value, $currency);
    }

    /**
     * Truncates text length and appends an ellipsis if necessary.
     *
     * @param string|null $dado Text to truncate.
     * @param int $qtdvisivel Number of visible characters.
     * @param string $abbreviator String appended at the end if truncated.
     * @return string Truncated text with ellipsis.
     */
    public static function limitText(string|null $text, int $length, string $abbreviator = "..."): string|null
    {
        if ($text === null) {
            return null;
        }
        $text = $text ?? '';
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . $abbreviator;
    }

    /**
     * Converts a string to camelCase (e.g.: "user_id" -> "userId").
     * @param string|null $string String to convert.
     * @return string|null Converted camelCase string, or null if input is null.
     */
    public static function toCamelCase(string|null $string): string|null
    {
        if ($string === null) {
            return null;
        }
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string))));
    }

    /**
     * Converts a string to PascalCase (e.g., "user_id" -> "UserId").
     * Useful for class names.
     * @param string|null $string String to convert.
     * @return string|null Converted PascalCase string, or null if input is null.
     */
    public static function toPascalCase(string|null $string): string|null
    {
        if ($string === null) {
            return null;
        }
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string)));
    }

    /**
     * Converts a string to snake_case (e.g., "userId" -> "user_id").
     * Useful for database column names.
     * @param string|null $string String to convert.
     * @return string|null Converted snake_case string, or null if input is null.
     */
    public static function toSnakeCase(string|null $string): string|null
    {
        if ($string === null) {
            return null;
        }
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    /**
     * Creates a URL slug (friendly URL).
     * E.g., "Hello World!" -> "hello-world"
     * @param string|null $string String to convert to slug.
     * @param string $separator Separator to use in slug (default "-").
     * @return string|null Resulting slug, or null if input is null.
     */
    public static function toSlug(string|null $string, string $separator = '-'): string|null
    {
        if ($string === null) {
            return null;
        }
        // Strip accents
        $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        // Remove special characters and convert to lowercase
        $string = preg_replace('/[^a-zA-Z0-9\s]/', '', strtolower($string));
        // Replace spaces with separator
        return preg_replace('/\s+/', $separator, trim($string));
    }

    /**
     * Converts bytes to human-readable size (KB, MB, GB).
     * E.g., 1024 -> "1 KB"
     * @param int|null $bytes Number of bytes to convert.
     * @param int $precision Decimal precision (default: 2).
     * @return string|null Converted readable size, or null if input is null.
     */
    public static function bytesToHuman(int|null $bytes, int $precision = 2): string|null
    {
        if ($bytes === null) {
            return null;
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, self::l_countStatic($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Removes all non-numeric characters from a string.
     *
     * @param string|null $input Input text for sanitization.
     * @return string|null Returns only digits found, or null if input is null.
     */
    public static function toNumbersOnly(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }

        return preg_replace('/\D/', '', $input) ?? '';
    }

    /**
     * Converts an XML string into an associative array.
     *
     * @param string|null $xmlString XML string.
     * @return array|null Returns converted array or null if input is null.
     *
     * @throws \InvalidArgumentException When provided XML is invalid.
     */
    public static function xmlToArray(string|null $xmlString): array|null
    {
        if ($xmlString === null) {
            return null;
        }
        $xml = simplexml_load_string($xmlString, "SimpleXMLElement", LIBXML_NOCDATA);
        if ($xml === false) {
            throw new \InvalidArgumentException("XML invalid.");
        }
        return json_decode(json_encode($xml), true);
    }

    /**
     * Converts a hexadecimal color to RGB format.
     *
     * Accepts values in #RGB, RGB, #RRGGBB, or RRGGBB formats.
     *
     * @param string|null $hex Color in hexadecimal format.
     * @return array|null Associative array with 'r', 'g', and 'b' keys, or null if input is null.
     */
    public static function hexToRgb(string|null $hex): array|null
    {
        if ($hex === null) {
            return null;
        }

        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return ['r' => $r, 'g' => $g, 'b' => $b];
    }

    /**
     * Retrieves an instance of the Luma class.
     * @return Luma Instance of the Luma class.
     */
    public function __debugInfo(): array
    {
        return [
            'Lumynus' => "Framework PHP"
        ];
    }
}
