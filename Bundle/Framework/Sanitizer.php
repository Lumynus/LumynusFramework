<?php

declare(strict_types=1);

namespace Lumynus\Bundle\Framework;

use Lumynus\Bundle\Framework\LumaClasses;

final class Sanitizer extends LumaClasses
{
    /**
     * Limpa uma string, removendo tags HTML e, opcionalmente, escapando entidades HTML.
     *
     * @param string|null|int|float $input A string ou número de entrada a ser sanitizada.
     * @param bool $escapeHtml Define se deve escapar HTML usando htmlspecialchars.
     * @return string|null A string sanitizada ou null se a entrada for null.
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
     * Remove todos os caracteres não numéricos e retorna o inteiro.
     *
     * @param int|string|float|null $input O valor a ser sanitizado.
     * @return int|null O valor convertido em inteiro ou null se o valor for null.
     */
    public static function int(int|string|float|null $input): int|null
    {
        if ($input === null) {
            return null;
        }
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Remove caracteres inválidos de um número decimal e retorna o float.
     *
     * @param int|string|float|null $input O valor a ser sanitizado.
     * @return float|null O valor convertido em float ou null se o valor for null.
     */
    public static function float(int|string|float|null $input): float|null
    {
        if ($input === null) {
            return null;
        }
        return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Remove caracteres inválidos de um e-mail.
     *
     * @param string|null $input O e-mail de entrada.
     * @return string|null O e-mail sanitizado ou null se a entrada for null.
     */
    public static function email(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return filter_var($input, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Remove caracteres inválidos de uma URL.
     *
     * @param string|null $input A URL de entrada.
     * @return string|null A URL sanitizada ou null se a entrada for null.
     */
    public static function url(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return filter_var($input, FILTER_SANITIZE_URL);
    }

    /**
     * Converte valores como "1", "true", "on" para booleano.
     *
     * @param mixed $input O valor de entrada.
     * @return bool|null O valor convertido em booleano ou null se a entrada for null.
     */
    public static function boolean(mixed $input): bool|null
    {
        if ($input === null) {
            return null;
        }
        return filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * Sanitiza um array recursivamente aplicando o tipo de sanitização definido.
     *
     * @param array $input Array a ser sanitizado.
     * @param string $type Tipo de sanitização: 'string', 'int', 'float', 'email', 'url', 'bool'.
     * @param bool $escapeHtml Se for do tipo 'string', define se escapa HTML.
     * @return array Array sanitizado com todos os valores convertidos.
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
     * Remove espaços, quebras de linha e substitui múltiplos espaços por apenas um.
     *
     * @param string|null $input A string de entrada.
     * @return string|null A string com espaços normalizados ou null se a entrada for null.
     */
    public static function normalizeWhitespace(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }

        return preg_replace('/\s+/', ' ', trim($input));
    }

    /**
     * Remove todos os números de uma string.
     *
     * @param string|null $input A string de entrada.
     * @return string|null A string sem números ou null se a entrada for null.
     */
    public static function removeNumbers(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }

        return preg_replace('/\d+/', '', $input);
    }

    /**
     * Remove caracteres especiais, mantendo letras, números e espaços.
     *
     * @param string $input A string de entrada.
     * @return string|null A string sem caracteres especiais ou null se a entrada for null.
     */
    public static function removeSpecialCharacters(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return preg_replace('/[^\w\s]/', '', $input);
    }

    /**
     * Remove todas as tags HTML e PHP.
     *
     * @param string $input A string de entrada.
     * @return string|null A string sem tags ou null se a entrada for null.
     */
    public static function removeHtmlTags(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return strip_tags($input);
    }

    /**
     * Remove acentuação de caracteres latinos comuns.
     *
     * @param string $input A string de entrada.
     * @return string|null A string sem acentos ou null se a entrada for null.
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
     * Remove todas as letras, mantendo apenas números e símbolos.
     *
     * @param string $input A string de entrada.
     * @return string|null A string sem letras ou null se a entrada for null.
     */
    public static function removeLetters(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return preg_replace('/[a-zA-Z]/', '', $input);
    }

    /**
     * Remove espaços duplicados em excesso.
     *
     * @param string $input A string de entrada.
     * @return string|null A string com espaços reduzidos ou null se a entrada for null.
     */
    public static function removeExtraSpaces(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }
        return preg_replace('/\s+/', ' ', trim($input));
    }

    /**
     * Remove quebras de linha e substitui por espaços simples.
     *
     * @param string $input A string de entrada.
     * @return string|null A string sem quebras de linha ou null se a entrada for null.
     */
    public static function removeLineBreaks(string|null $input): string|null
    {
        if ($input === null) {
            return null;
        }

        return str_replace(["\r", "\n"], ' ', $input);
    }

    /**
     * Método para obter a instância da classe Luma.
     * @return Luma Retorna uma nova instância da classe Luma.
     */
    public function __debugInfo(): array
    {
        return [
            'Lumynus' => "Framework PHP"
        ];
    }
}
