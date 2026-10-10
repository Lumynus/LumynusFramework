<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;

final class Sanitizer extends LumaClasses
{
    /**
     * Cleans a string, stripping HTML tags and optionally escaping HTML entities.
     *
     * @param string|null|int|float $input Input string or number to sanitize.
     * @param bool $escapeHtml Whether to escape HTML using htmlspecialchars.
     * @return string|null Sanitized string, or null if input is null.
     */
    public static function string(string|null|int|float $input, bool $escapeHtml = true): string|null
    {
        if ($input === null) {
            return null;
        }

        $input = strip_tags((string)$input);
        if ($escapeHtml) {
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        return trim($input);
    }

    /**
     * Removes all non-numeric characters and returns integer.
     *
     * @param int|string|float|null $input Value to sanitize.
     * @return int|null Converted integer, or null if value is null.
     */
    public static function int(int|string|float|null $input): int|null
    {
        if ($input === null) {
            return null;
        }
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Removes invalid characters from a decimal number and returns float.
     *
     * @param int|string|float|null $input Value to sanitize.
     * @return float|null Converted float, or null if value is null.
     */
    public static function float(int|string|float|null $input): float|null
    {
        if ($input === null) {
            return null;
        }
        return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Removes invalid characters from an email address.
     *
     * @param string|null $input Input email.
     * @return string|null Sanitized email, or null if input is null.
     */
    public static function email(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return filter_var($input, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Removes invalid characters from a URL.
     *
     * @param string|null $input Input URL.
     * @return string|null Sanitized URL, or null if input is null.
     */
    public static function url(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return filter_var($input, FILTER_SANITIZE_URL);
    }

    /**
     * Converts values like "1", "true", "on" to boolean.
     *
     * @param mixed $input Input value.
     * @return bool|null Converted boolean, or null if input is null.
     */
    public static function boolean(mixed $input): bool|null
    {
        if ($input === null) {
            return null;
        }
        return filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * Sanitizes an array recursively applying the specified sanitization type.
     *
     * @param array $input Array to sanitize.
     * @param string $type Sanitization type: 'string', 'int', 'float', 'email', 'url', 'bool'.
     * @param bool $escapeHtml If 'string' type, defines whether to escape HTML.
     * @return array Sanitized array with converted values.
     */
    public static function array(array $input, string $type = 'string', bool $escapeHtml = true): array
    {
        return array_map(function ($value) use ($type, $escapeHtml) {
            if (is_array($value)) {
                return self::array($value, $type, $escapeHtml);
            }

            switch ($type) {
                case 'string':
                    return self::string($value, $escapeHtml);
                case 'int':
                    return self::int($value);
                case 'float':
                    return self::float($value);
                case 'email':
                    return self::email($value);
                case 'url':
                    return self::url($value);
                case 'bool':
                    return self::boolean($value);
                default:
                    throw new \InvalidArgumentException("Invalid sanitization type: {$type}");
            }
        }, $input);
    }

    /**
     * Removes whitespace, line breaks, and collapses multiple spaces into a single space.
     *
     * @param string|null $input Input string.
     * @return string|null String with normalized whitespace, or null if input is null.
     */
    public static function normalizeWhitespace(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }

        return preg_replace('/\s+/', ' ', trim($input));
    }

    /**
     * Removes all numbers from a string.
     *
     * @param string|null $input Input string.
     * @return string|null String without numbers, or null if input is null.
     */
    public static function removeNumbers(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }

        return preg_replace('/\d+/', '', $input);
    }

    /**
     * Removes special characters, preserving letters, numbers, and spaces.
     *
     * @param string $input Input string.
     * @return string|null String without special characters, or null if input is null.
     */
    public static function removeSpecialCharacters(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return preg_replace('/[^\w\s]/', '', $input);
    }

    /**
     * Strips all HTML and PHP tags.
     *
     * @param string $input Input string.
     * @return string|null String without tags, or null if input is null.
     */
    public static function removeHtmlTags(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return strip_tags($input);
    }

    /**
     * Strips accents from common Latin characters.
     *
     * @param string $input Input string.
     * @return string|null Accent-stripped string, or null if input is null.
     */
    public static function removeAccents(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        $unwanted_array = [
            'á' => 'a',
            'à' => 'a',
            'ã' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'õ' => 'o',
            'ô' => 'o',
            'ö' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ç' => 'c',
            'ñ' => 'n'
        ];
        return strtr($input, $unwanted_array);
    }

    /**
     * Removes all letters, keeping only numbers and symbols.
     *
     * @param string $input Input string.
     * @return string|null String without letters, or null if input is null.
     */
    public static function removeLetters(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return preg_replace('/[a-zA-Z]/', '', $input);
    }

    /**
     * Removes redundant duplicated spaces.
     *
     * @param string $input Input string.
     * @return string|null String with reduced spaces, or null if input is null.
     */
    public static function removeExtraSpaces(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return preg_replace('/\s+/', ' ', trim($input));
    }

    /**
     * Removes line breaks and replaces them with single spaces.
     *
     * @param string $input Input string.
     * @return string|null String without line breaks, or null if input is null.
     */
    public static function removeLineBreaks(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }

        return str_replace(["\r", "\n"], ' ', $input);
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
