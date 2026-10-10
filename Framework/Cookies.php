<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\Config;
use Lumynus\Framework\Logs;
use Lumynus\Framework\LumaClasses;

final class Cookies extends LumaClasses implements \Lumynus\Contracts\CookieInterface
{
    private static ?self $activeInstance = null;
    private array $cookieParams = [];
    private string $secretKey;
    private const PREFIX = 'LUM_';

    /**
     * Constructor for secure initialization and configuration of cookies.
     *
     * @param array $userOptions Custom cookie options (path, domain, secure, httponly, samesite).
     */
    public function __construct(array $userOptions = [])
    {
        if (!in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            throw new \RuntimeException('AES-256-GCM not supported on this server.');
        }

        $cookieConfig = Config::getApplicationConfig()['security']['cookie'] ?? [];
        $secret = $cookieConfig['secret'] ?? 'LumynusApp';

        if (Config::productionMode() && ($secret === 'LumynusApp' || strlen($secret) < 16)) {
            Logs::register('Cookies', 'Warning: Cookie secret key is insecure or using default in production mode.');
        }

        $this->secretKey = $this->generateSecretKey($secret);

        $defaults = [
            'path'     => '/',
            'domain'   => Config::getApplicationConfig()['App']['domain'] ?? '',
            'secure'   => Config::productionMode(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        $this->cookieParams = array_merge($defaults, $userOptions);
        self::$activeInstance = $this;
    }

    /**
     * Returns the active configured instance for current request, if any.
     *
     * @return self|null
     */
    public static function getActiveInstance(): ?self
    {
        return self::$activeInstance;
    }

    /**
     * Generates secret key based on configured secret via HKDF.
     *
     * @param string $secret
     * @return string
     */
    private function generateSecretKey(string $secret): string
    {
        return hash_hkdf(
            'sha256',
            $secret,
            32,
            'LumynusCookieEncryption',
            'LumynusSalt'
        );
    }

    /**
     * Returns cookie name prefixed with framework prefix.
     *
     * @param string $key
     * @return string
     */
    private function getRealKey(string $key): string
    {
        if (str_starts_with($key, self::PREFIX)) {
            return $key;
        }
        return self::PREFIX . $key;
    }

    /**
     * Sets a cookie with authenticated encryption (AES-256-GCM).
     *
     * @param string $key Cookie name.
     * @param mixed $value Cookie value.
     * @param int $expire Expiration duration in seconds (0 for session).
     * @param array $options Additional cookie options (path, domain, secure, httponly, samesite).
     * @return void
     */
    public function set(
        string $key,
        mixed $value,
        int $expire = 0,
        array $options = []
    ): void {
        $realKey = $this->getRealKey($key);
        $expireTime = $expire > 0 ? time() + $expire : 0;
        $params = array_merge($this->cookieParams, $options);

        if (PHP_SAPI !== 'cli' && headers_sent($file, $line)) {
            $msg = "Cannot set cookie '{$realKey}': headers already sent in {$file}:{$line}";
            Logs::register('Cookies', $msg);
            if (!Config::productionMode()) {
                throw new \RuntimeException($msg);
            }
            return;
        }

        try {
            $envelope = [
                'v'   => $value,
                'exp' => $expireTime,
            ];

            $payload = json_encode($envelope, JSON_THROW_ON_ERROR);
            $iv = random_bytes(12);
            $aad = $realKey;

            $ciphertext = openssl_encrypt(
                $payload,
                'aes-256-gcm',
                $this->secretKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad
            );

            if ($ciphertext === false) {
                throw new \RuntimeException("Failed to encrypt cookie '{$realKey}'.");
            }

            $cookieValue = base64_encode($iv . $tag . $ciphertext);

            // RFC 6265: Max 4096 bytes total per cookie. Leaving margin for attributes.
            $maxAllowed = 4096 - strlen($realKey) - 250;
            if (strlen($cookieValue) > $maxAllowed) {
                $sizeMsg = sprintf(
                    'Cookie "%s" size exceeded (%d bytes, maximum allowed is %d bytes).',
                    $realKey,
                    strlen($cookieValue),
                    $maxAllowed
                );
                Logs::register('Cookies', $sizeMsg);
                if (!Config::productionMode()) {
                    throw new \RuntimeException($sizeMsg);
                }
                return;
            }

            $cookieOptions = $params;
            $cookieOptions['expires'] = $expireTime;

            setcookie($realKey, $cookieValue, $cookieOptions);
            $_COOKIE[$realKey] = $cookieValue;
        } catch (\Throwable $e) {
            Logs::register('Cookies', "Error setting cookie '{$realKey}': " . $e->getMessage());
            if (!Config::productionMode()) {
                throw $e;
            }
        }
    }

    /**
     * Retrieves and validates a cookie, returning its decrypted value.
     *
     * @param string $key Cookie name.
     * @return mixed Cookie value or null if not found or invalid.
     */
    public function get(string $key): mixed
    {
        $realKey = $this->getRealKey($key);
        $success = false;
        $val = $this->decryptCookie($realKey, $success);

        return $success ? $val : null;
    }

    /**
     * Checks whether cookie exists in request and is intact/valid.
     * If data is corrupted, clears key and returns false.
     *
     * @param string $key Cookie name.
     * @return bool True if cookie exists and is valid, false otherwise.
     */
    public function has(string $key): bool
    {
        $realKey = $this->getRealKey($key);
        if (!isset($_COOKIE[$realKey]) || !is_string($_COOKIE[$realKey])) {
            return false;
        }

        $success = false;
        $this->decryptCookie($realKey, $success);

        if (!$success) {
            unset($_COOKIE[$realKey]);
            return false;
        }

        return true;
    }

    /**
     * Removes a cookie from browser and from $_COOKIE superglobal.
     *
     * @param string $key Cookie name.
     * @param array $options Additional cookie options (path, domain, secure, httponly, samesite).
     * @return void
     */
    public function remove(string $key, array $options = []): void
    {
        $realKey = $this->getRealKey($key);

        if (PHP_SAPI !== 'cli' && headers_sent($file, $line)) {
            unset($_COOKIE[$realKey]);
            Logs::register('Cookies', "Cannot remove cookie '{$realKey}' from browser: headers already sent in {$file}:{$line}");
            return;
        }

        $params = array_merge($this->cookieParams, $options);

        setcookie($realKey, '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);

        unset($_COOKIE[$realKey]);
    }

    /**
     * Clears all cookies managed by the framework.
     *
     * @return void
     */
    public function clear(): void
    {
        foreach (array_keys($_COOKIE) as $key) {
            if (is_string($key) && str_starts_with($key, self::PREFIX)) {
                $cleanKey = substr($key, strlen(self::PREFIX));
                $this->remove($cleanKey);
            }
        }
    }

    /**
     * Regenerates all valid cookies, re-encrypting values and preserving
     * remaining original expiration time.
     *
     * @return void
     */
    public function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && headers_sent($file, $line)) {
            Logs::register('Cookies', "Cannot regenerate cookies: headers already sent in {$file}:{$line}");
            return;
        }

        foreach ($_COOKIE as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, self::PREFIX)) {
                continue;
            }

            $cleanKey = substr($key, strlen(self::PREFIX));
            $success = false;
            $exp = 0;
            $val = $this->decryptCookie($key, $success, $exp);

            if ($success) {
                $remaining = $exp > time() ? ($exp - time()) : 0;
                $this->set($cleanKey, $val, $remaining);
            }
        }
    }

    /**
     * Generates a unique identifier for current set of valid cookies.
     *
     * @return string Unique ID based on cookie contents.
     */
    public function getId(): string
    {
        $all = $this->getAll();
        ksort($all);
        return hash('sha256', json_encode($all));
    }

    /**
     * Retrieves all valid cookies as an associative array.
     *
     * @return array Associative array of all valid cookies.
     */
    public function getAll(): array
    {
        $all = [];
        foreach ($_COOKIE as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, self::PREFIX)) {
                continue;
            }

            $cleanKey = substr($key, strlen(self::PREFIX));
            $success = false;
            $val = $this->decryptCookie($key, $success);
            if ($success) {
                $all[$cleanKey] = $val;
            }
        }
        return $all;
    }

    /**
     * Decrypts and validates integrity of a cookie.
     *
     * @param string $realKey Full prefixed key.
     * @param bool &$success Indicates whether decoding succeeded.
     * @param int &$exp Stores expiration timestamp of the cookie.
     * @return mixed
     */
    private function decryptCookie(string $realKey, bool &$success = false, int &$exp = 0): mixed
    {
        $success = false;
        $exp = 0;

        if (!isset($_COOKIE[$realKey]) || !is_string($_COOKIE[$realKey])) {
            return null;
        }

        $decoded = base64_decode($_COOKIE[$realKey], true);
        if ($decoded === false || strlen($decoded) < 28) {
            return null;
        }

        $iv         = substr($decoded, 0, 12);
        $tag        = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        $aad        = $realKey;

        $plain = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->secretKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plain === false) {
            Logs::register('Cookies', "Integrity failure in cookie '{$realKey}': possible tampering or invalid secret key.");
            return null;
        }

        try {
            $data = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);

            // Checks if in envelope format with expiration
            if (is_array($data) && array_key_exists('v', $data) && array_key_exists('exp', $data)) {
                $exp = (int) $data['exp'];
                if ($exp > 0 && time() > $exp) {
                    // Cookie expirado internamente (anti-replay)
                    return null;
                }
                $success = true;
                return $data['v'];
            }

            // Backwards compatibility with cookies stored without envelope
            $success = true;
            return $data;
        } catch (\JsonException $e) {
            Logs::register('Cookies', "JSON decoding failed for cookie '{$realKey}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Class debug information.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'Lumynus' => "Framework PHP"
        ];
    }
}
