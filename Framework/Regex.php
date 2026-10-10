<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;
use Lumynus\Framework\Logs;

final class Regex extends LumaClasses
{
    /**
     * Tests whether a value matches a regular expression.
     *
     * @param string $regex Regular expression pattern (e.g. Requirements::EMAIL).
     * @param mixed $value Value to be tested.
     * @return bool
     */
    public static function test(string $regex, mixed $value = ""): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        try {
            return preg_match($regex, (string) $value) === 1;
        } catch (\Throwable $e) {
            Logs::register(
                'Invalid regex pattern',
                sprintf(
                    'Pattern: %s | Value: %s | Error: %s',
                    $regex,
                    is_scalar($value) ? (string) $value : gettype($value),
                    $e->getMessage()
                )
            );

            return false;
        }
    }

    /**
     * Refines a string keeping ONLY allowed characters in the Regex.
     * NOTICE: This method only works reliably for character-class-based Regex
     * (e.g., [A-Za-z], \d, \s).
     * DO NOT USE to refine Emails, URLs, or Dates.
     *
     * @param string $regex Regex defining what is ALLOWED (e.g. Requirements::TEXT_ONLY).
     * @param string $value Value to sanitize.
     * @return string
     */
    public static function refine(string $regex, string $value = ""): string
    {
        if ($value === "") {
            return "";
        }

        try {
            $allowedChars = self::extractCharacterClass($regex);

            if ($allowedChars === null) {
                return $value;
            }
            $cleaningRegex = '/[^' . $allowedChars . ']+/u';

            return preg_replace($cleaningRegex, '', $value) ?? "";
        } catch (\Throwable $e) {
            Logs::register(
                'Invalid regex',
                'Error: ' . $e->getMessage()
            );

            return $value;
        }
    }

    /**
     * Attempts to extract allowed character set from a Regex pattern.
     *
     * @param string $regex
     * @return string|null
     */
    private static function extractCharacterClass(string $regex): ?string
    {
        // Strip common delimiters (#, /, ~) and trailing modifiers
        $pattern = preg_replace('/^([\/#~])|([\/#~][a-z]*)$/i', '', $regex);

        // Remove start/end anchors if present (^, $)
        $pattern = trim($pattern, '^$');

        // Strip quantifiers (+, *, ?, {n,m})
        $pattern = preg_replace('/[\+\*\?]+$/', '', $pattern);
        $pattern = preg_replace('/\{\d+(,\d*)?\}$/', '', $pattern);

        // Case 1: Regex is explicitly a character class, e.g.: [A-Z0-9]
        // Extract contents from within first brackets
        if (preg_match('/^\[(.*)\]$/', $pattern, $matches)) {
            return $matches[1];
        }

        // Case 2: Regex uses shorthand without brackets, e.g. \d+
        // Map common shorthand character classes to their regex equivalents
        $allowed = "";
        $found = false;

        if (str_contains($pattern, '\d')) {
            $allowed .= '0-9';
            $found = true;
        }
        if (str_contains($pattern, '\w')) {
            $allowed .= 'A-Za-z0-9_';
            $found = true;
        }
        if (str_contains($pattern, '\s')) {
            $allowed .= '\s';
            $found = true;
        }

        // If shorthand character class found, return composed character set
        if ($found) {
            return $allowed;
        }

        // Se a regex for complexa (grupos, pipes, lookaheads), aborta.
        // Ex: (abc|def) ou (?=.*)
        return null;
    }
}
