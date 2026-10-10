<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;
use Lumynus\Framework\Config;

final class Encryption extends LumaClasses
{
    /**
     * Encrypts content using AES-256-GCM.
     *
     * @param string|null $data Content to encrypt
     * @param string|null $keyName Name of PEM key to use
     *
     * @return string Base64 encoded encrypted payload
     *
     * @throws \InvalidArgumentException If content is empty or null
     * @throws \RuntimeException If OpenSSL is unavailable or encryption fails
     */
    public static function encrypt(string|null $data, ?string $keyName = null): string
    {
        if ($data === null || $data === '') {
            throw new \InvalidArgumentException('Data to encrypt cannot be null or empty.');
        }

        self::verificaExtensaoOpenSSL();

        if (!in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            throw new \RuntimeException('AES-256-GCM not supported on this server.');
        }

        $key = self::obterChave($keyName);

        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Error encrypting data.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts content encrypted with AES-256-GCM.
     *
     * @param string|null $data Base64 encoded encrypted payload
     * @param string|null $keyName Name of PEM key to use
     *
     * @return string Decrypted content
     *
     * @throws \InvalidArgumentException If content is empty or null
     * @throws \RuntimeException If decryption fails
     */
    public static function decrypt(string|null $data, ?string $keyName = null): string
    {
        if ($data === null || $data === '') {
            throw new \InvalidArgumentException('Data to decrypt cannot be null or empty.');
        }

        self::verificaExtensaoOpenSSL();

        $raw = base64_decode($data, true);
        if ($raw === false) {
            throw new \RuntimeException('Invalid base64 data.');
        }

        $ivLength  = 12;
        $tagLength = 16;

        $iv  = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, $tagLength);
        $ciphertext = substr($raw, $ivLength + $tagLength);

        $key = self::obterChave($keyName);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Error decrypting data.');
        }

        return $plaintext;
    }

    /**
     * Encrypts content using AES-256-GCM and encodes the result
     * in URL-safe Base64 without padding.
     *
     * @param string $data Content to encrypt.
     * @param string|null $keyName Name of PEM key to use.
     *
     * @return string Encrypted URL-safe encoded content.
     *
     * @throws \InvalidArgumentException If content is empty or invalid.
     * @throws \RuntimeException If OpenSSL is unavailable or encryption fails.
     */
    public static function encryptToUrl(string $data, ?string $keyName = null): string
    {
        return rtrim(strtr(self::encrypt($data, $keyName), '+/', '-_'), '=');
    }

    /**
     * Decrypts URL-safe encoded AES-256-GCM encrypted content.
     *
     * @param string $data URL-safe Base64 encoded encrypted content.
     * @param string|null $keyName Name of PEM key to use.
     *
     * @return string Decrypted content.
     *
     * @throws \InvalidArgumentException If content is empty or invalid.
     * @throws \RuntimeException If decryption fails.
     */
    public static function decryptFromUrl(string $data, ?string $keyName = null): string
    {
        return self::decrypt(strtr($data, '-_', '+/'), $keyName);
    }

    /**
     * Checks whether a PEM key file exists.
     * @param string|null $keyName Key name (without extension)
     * @return bool True if key exists, false otherwise
     */
    public static function hasKey(?string $keyName = null): bool
    {
        $keyDir = Config::projectPath() .
            DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'keys';

        $keyName = self::sanitizeFileName($keyName ?? 'key');
        $keyFile = $keyDir . DIRECTORY_SEPARATOR . $keyName . '.pem';

        return file_exists($keyFile);
    }

    /**
     * Generates a new 32-byte AES key and saves it to a PEM file.
     *
     * @param string|null $keyName Key name (without extension)
     *
     * @return string Full path to the created key file
     *
     * @throws \RuntimeException If directory or file cannot be created
     */
    public static function createKey(?string $keyName = null): string
    {
        $keyDir = Config::projectPath() .
            DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'keys';

        if (!is_dir($keyDir) && !mkdir($keyDir, 0755, true)) {
            throw new \RuntimeException("Failed to create key directory: {$keyDir}");
        }

        $keyName = self::sanitizeFileName($keyName ?? 'key');
        $keyFile = $keyDir . DIRECTORY_SEPARATOR . $keyName . '.pem';

        $randomKey = random_bytes(32);


        $tmpFile = $keyFile . '.tmp';

        if (file_put_contents($tmpFile, $randomKey, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write temp key file: {$tmpFile}");
        }

        if (!rename($tmpFile, $keyFile)) {
            @unlink($tmpFile);
            throw new \RuntimeException("Failed to move temp key file to final location: {$keyFile}");
        }
        if (!chmod($keyFile, 0600)) {
            throw new \RuntimeException("Failed to set permissions on key file: {$keyFile}");
        }
        return $keyFile;
    }

    /**
     * Removes an existing PEM key.
     *
     * @param string|null $keyName Key name (without extension)
     *
     * @return bool True if removed or if key does not exist
     */
    public static function removeKey(?string $keyName = null): bool
    {
        $keyName = self::sanitizeFileName($keyName ?? 'key');

        $keyFile = Config::projectPath() .
            DIRECTORY_SEPARATOR . 'storage' .
            DIRECTORY_SEPARATOR . 'keys' .
            DIRECTORY_SEPARATOR . $keyName . '.pem';

        if (!file_exists($keyFile)) return true;

        return @unlink($keyFile);
    }

    /**
     * Serializes and encrypts a value, saving it to a .luma file.
     *
     * @param string $nameFile File name (without extension)
     * @param mixed $value Value to serialize and encrypt
     * @param string|null $keyName Name of PEM key
     *
     * @return bool True on success
     */
    public static function saveToFile(string $nameFile, $value, ?string $keyName = null): bool
    {
        $dir = Config::projectPath() .
            DIRECTORY_SEPARATOR . 'storage' .
            DIRECTORY_SEPARATOR . 'encryptions';

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) return false;

        $nameFile = self::sanitizeFileName($nameFile);
        $filePath = $dir . DIRECTORY_SEPARATOR . $nameFile . '.luma';

        $serialized = serialize($value);
        try {
            $encrypted = self::encrypt($serialized, $keyName);
        } catch (\RuntimeException $e) {
            return false;
        }

        $tmp = $filePath . '.tmp';

        if (file_put_contents($tmp, $encrypted, LOCK_EX) === false) {
            return false;
        }

        return rename($tmp, $filePath);
    }

    /**
     * Removes an encrypted (.luma) file.
     *
     * @param string $nameFile File name (without extension)
     *
     * @return bool True if removed or if file does not exist
     */
    public static function removeToFile(string $nameFile): bool
    {
        $dir = Config::projectPath() .
            DIRECTORY_SEPARATOR . 'storage' .
            DIRECTORY_SEPARATOR . 'encryptions';

        $nameFile = self::sanitizeFileName($nameFile);
        $filePath = $dir . DIRECTORY_SEPARATOR . $nameFile . '.luma';

        if (!file_exists($filePath)) return true;

        return @unlink($filePath);
    }

    /**
     * Reads, decrypts and deserializes .luma files.
     *
     * @param string|array $nameFile File name or list of file names
     * @param string|null $keyName Name of PEM key
     *
     * @return mixed|array Returns deserialized value or array of values
     *
     * @throws \RuntimeException If file does not exist or decryption fails
     */
    public static function readFiles(string|array $nameFile, ?string $keyName = null)
    {
        $dir = Config::projectPath() .
            DIRECTORY_SEPARATOR . 'storage' .
            DIRECTORY_SEPARATOR . 'encryptions';

        $files = is_array($nameFile) ? $nameFile : [$nameFile];
        $results = [];

        foreach ($files as $file) {
            $file = self::sanitizeFileName($file);
            $filePath = $dir . DIRECTORY_SEPARATOR . $file . '.luma';

            if (!file_exists($filePath)) {
                throw new \RuntimeException("File not found: {$filePath}");
            }

            $encrypted = file_get_contents($filePath);
            if ($encrypted === false) {
                throw new \RuntimeException("Failed to read file: {$filePath}");
            }

            $decrypted = self::decrypt($encrypted, $keyName);

            $value = @unserialize($decrypted, ['allowed_classes' => false]);
            if ($value === false && $decrypted !== serialize(false)) {
                throw new \RuntimeException("Failed to unserialize data from file: {$filePath}");
            }

            $results[$file] = $value;
        }

        return self::l_countStatic($results) === 1 ? array_shift($results) : $results;
    }

    /**
     * Verifies that OpenSSL extension is enabled.
     *
     * @throws \RuntimeException If OpenSSL is unavailable
     */
    private static function verificaExtensaoOpenSSL(): void
    {
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException("The OpenSSL extension is not enabled in PHP.");
        }
    }

    /**
     * Retrieves the 32-byte AES key from a PEM file.
     *
     * @param string|null $keyName Key name
     *
     * @return string 32-byte AES key binary
     *
     * @throws \RuntimeException If key does not exist or is invalid
     */
    private static function obterChave(?string $keyName): string
    {
        $keyName = self::sanitizeFileName($keyName ?? 'key');

        if (str_contains($keyName, '.pem')) {
            $keyName = str_ireplace('.pem', '', $keyName);
        }

        $caminho = Config::projectPath() .
            DIRECTORY_SEPARATOR . 'storage' .
            DIRECTORY_SEPARATOR . 'keys' .
            DIRECTORY_SEPARATOR .
            $keyName . '.pem';

        if (!file_exists($caminho)) {
            throw new \RuntimeException("Key file not found: {$caminho}");
        }

        $keyContent = file_get_contents($caminho);
        if ($keyContent === false) {
            throw new \RuntimeException("Failed to read key file.");
        }
        $chave = substr($keyContent, 0, 32);

        if (strlen($chave) !== 32) {
            throw new \RuntimeException("The key must be at least 32 bytes long.");
        }

        return $chave;
    }

    /**
     * Sanitizes file names to prevent Path Traversal attacks.
     *
     * @param string $name Original file name
     *
     * @return string Sanitized file name
     *
     * @throws \InvalidArgumentException If file name is invalid
     */
    private static function sanitizeFileName(string $name): string
    {
        $filename = basename($name);
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filename);
        if (empty($filename)) {
            throw new \InvalidArgumentException("Invalid file name provided.");
        }
        return $filename;
    }
}
