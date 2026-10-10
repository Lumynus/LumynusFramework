<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

/**
 * Trait providing strict regular expressions for data validation.
 * Covers: Text, Numeric, BR Documents, Internet, Security, Files and Miscellaneous.
 *
 * @package Lumynus\Framework
 */
trait Requirements
{
    // =========================================================================
    // 📌 1. TEXT AND FORMATTING
    // =========================================================================

    /**
     * Letters only (with accents) and single spaces.
     * E.g.: "First Last"
     */
    public const TEXT_ONLY = "/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/";

    /**
     * Alias for TEXT_ONLY (retained for semantic compatibility).
     */
    public const WORDS_WITH_SPACES = "/^[A-Za-zÀ-ÖØ-öø-ÿ\s]+$/";

    /**
     * Letters only, without spaces or numbers.
     */
    public const LETTERS_ONLY = "/^[A-Za-zÀ-ÖØ-öø-ÿ]+$/";

    /**
     * Strict full name (Minimum 2 names).
     * Prevents duplicate spaces or trailing spaces.
     */
    public const NAME = "/^[A-Za-zÀ-ÖØ-öø-ÿ]+(?:\s[A-Za-zÀ-ÖØ-öø-ÿ]+)+$/";

    /**
     * Alphanumeric text (letters, numbers and accents). No symbols.
     */
    public const ALPHANUMERIC = "/^[A-Za-z0-9À-ÖØ-öø-ÿ]+$/";

    /**
     * Slug for SEO friendly URLs. E.g.: "my-post-2024".
     */
    public const SLUG = "/^[a-z0-9]+(?:-[a-z0-9]+)*$/";

    /**
     * Paragraph: allows common punctuation (.,;!?'"()-).
     */
    public const PARAGRAPH = "/^[\wÀ-ÖØ-öø-ÿ\s.,;!?\"'()\-\n\r]+$/";

    /**
     * Checks whether string contains ONLY whitespace characters.
     */
    public const WHITESPACE_ONLY = "/^\s+$/";

    // =========================================================================
    // 📌 2. NUMERICAL, FINANCIAL AND SCIENTIFIC
    // =========================================================================

    public const WHOLE = "/^\d+$/";                 // Inteiro positivo
    public const INT = "/^-?\d+$/";                 // Inteiro (pos/neg)
    public const FLOAT = "/^-?\d+(\.\d+)?$/";       // Decimal

    /**
     * Scientific notation. E.g.: 1.2e3, -5E-4.
     */
    public const SCIENTIFIC = "/^-?\d+(\.\d+)?([eE][-+]?\d+)?$/";

    /**
     * Binary (0 and 1).
     */
    public const BINARY = "/^[01]+$/";

    /**
     * Numeric hexadecimal. E.g.: 0xFF, 1A3B.
     */
    public const HEXADECIMAL = "/^[A-Fa-f0-9]+$/";

    /**
     * Currency (with or without symbol). E.g.: R$ 1.000,00 or 100.50.
     */
    public const CURRENCY = "/^([A-Z$]{1,3}\s?)?\d{1,3}(\.?\d{3})*(,\d{2})?$/";

    /**
     * Credit card (Basic format validation 13-19 digits).
     */
    public const CREDIT_CARD = "/^\d{13,19}$/";

    /**
     * Coordenadas (Lat: -90 a 90, Long: -180 a 180).
     */
    public const COORDINATES = "/^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/";

    // =========================================================================
    // 📌 3. INTERNET AND NETWORKING (CRITICAL)
    // =========================================================================

    /**
     * Strict email (Disallows consecutive dots or dots at start/end).
     */
    public const EMAIL = "/^[a-zA-Z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/";

    /**
     * Valid URL (HTTP/HTTPS).
     */
    public const URL = "/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?(\?[\w=&-]*)?(#[\w-]*)?$/";

    /**
     * IPv4 Real (0-255).
     */
    public const IPV4 = "/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/";

    public const IPV6 = "/^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/";

    /**
     * MAC address (hardware). E.g.: 00:1A:2B:3C:4D:5E.
     */
    public const MAC_ADDRESS = "/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/";

    // =========================================================================
    // 📌 4. BRAZILIAN DOCUMENTS AND STANDARDS
    // =========================================================================

    public const CPF = "/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/";
    public const CNPJ = "/^\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}$/";
    public const CEP = "/^\d{5}-?\d{3}$/";

    /**
     * Brazilian phone number (9-digit mobile or landline). E.g.: (11) 91234-5678.
     */
    public const PHONE = "/^\(?\d{2}\)?\s?(?:9\d{4}|\d{4})-?\d{4}$/";

    /**
     * License plate (Mercosur and legacy formats).
     */
    public const VEHICLE_PLATE = "/^[A-Z]{3}-?\d{4}$|^[A-Z]{3}\d[A-Z]\d{2}$/";

    // =========================================================================
    // 📌 5. DATAS E HORAS
    // =========================================================================

    /**
     * Brazilian date (DD/MM/YYYY). Validates days 01-31 and months 01-12.
     */
    public const DATE_BR = "/^(0[1-9]|[12]\d|3[01])\/(0[1-9]|1[0-2])\/\d{4}$/";

    /**
     * ISO Date (YYYY-MM-DD). Standard database format.
     */
    public const DATE_ISO = "/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/";

    /**
     * Time (HH:MM or HH:MM:SS) in 24-hour format.
     */
    public const TIME = "/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/";

    /**
     * Time in 12-hour format (e.g.: 02:30 PM).
     */
    public const TIME_12H = "/^(0[1-9]|1[0-2]):[0-5]\d\s?(AM|PM|am|pm)$/";

    /**
     * Unix Timestamp (Seconds since 1970).
     */
    public const UNIX_TIMESTAMP = "/^\d{10}$/";

    // =========================================================================
    // 📌 6. SECURITY AND TOKENS
    // =========================================================================

    /**
     * Strong password (Min 8 chars, 1 letter, 1 number, 1 special char).
     */
    public const SECURE_PASSWORD = "/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/";

    /**
     * Very strong password (Min 12, upper, lower, number, special char).
     */
    public const VERY_STRONG_PASSWORD = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{12,}$/";

    /**
     * Numeric PIN password (4 to 8 digits).
     */
    public const NUMERIC_PASSWORD = "/^\d{4,8}$/";

    /**
     * Generic token (32 to 128 chars).
     */
    public const SECURE_TOKEN = "/^[A-Za-z0-9\-_]{32,128}$/";

    /**
     * UUID (v4 or generic).
     */
    public const UUID = "/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/";

    /**
     * JWT (JSON Web Token).
     */
    public const JWT = "/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]*$/";

    // =========================================================================
    // 📌 7. FILES, COLORS AND STYLES
    // =========================================================================

    /**
     * Hex Color (CSS). Ex: #FFF, #000000, #000000FF.
     */
    public const HEX_COLOR = "/^#?([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/";

    /**
     * Unidade CSS (px, em, rem, %, vh, etc). Ex: 10px, 2.5rem.
     */
    public const CSS_UNIT = "/^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|pt|cm|mm|in|pc)$/i";

    public const SAFE_FILENAME = "/^[A-Za-z0-9\-_]+\.[a-z0-9]+$/i";
    public const FILE_EXTENSION = "/^.*\.(jpg|jpeg|png|gif|webp|svg|pdf|doc|docx|xls|xlsx|csv|txt|zip|rar|mp4|mp3)$/i";

    // =========================================================================
    // 📌 8. IDENTIFICADORES E SOCIAIS
    // =========================================================================

    public const BARCODE = "/^\d{8,14}$/";          // EAN-8, EAN-13, EAN-14
    public const SKU = "/^[A-Za-z0-9\-_]{3,20}$/";  // SKU Produto

    /**
     * Social media handle (Twitter/X, Instagram). Optional leading @.
     */
    public const SOCIAL_USERNAME = "/^@?[A-Za-z0-9_]{3,25}$/";

    /**
     * Link de Rede Social (Facebook, X, Instagram, LinkedIn, Youtube, TikTok).
     */
    public const SOCIAL_LINK = "/^(https?:\/\/)?(www\.)?(facebook|twitter|x|instagram|linkedin|youtube|tiktok)\.com\/[A-Za-z0-9_.\/-]+$/";

    /**
     * Crypto address (Bitcoin Legacy and standard SegWit).
     */
    public const CRYPTO_ADDRESS = "/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-zA-HJ-NP-Z0-9]{39,59}$/";
}
