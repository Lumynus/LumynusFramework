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
     * Construtor para inicialização e configuração segura dos cookies.
     *
     * @param array $userOptions Opções personalizadas para os cookies (path, domain, secure, httponly, samesite).
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
     * Retorna a instância ativa configurada para a requisição atual, se houver.
     *
     * @return self|null
     */
    public static function getActiveInstance(): ?self
    {
        return self::$activeInstance;
    }

    /**
     * Gera a chave secreta baseada no secret configurado via HKDF.
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
     * Retorna o nome do cookie com o prefixo do framework.
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
     * Define um cookie com criptografia autenticada (AES-256-GCM).
     *
     * @param string $key Nome do cookie.
     * @param mixed $value Valor do cookie.
     * @param int $expire Tempo de expiração em segundos (0 para sessão).
     * @param array $options Opções adicionais para o cookie (path, domain, secure, httponly, samesite).
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

            // RFC 6265: Máximo de 4096 bytes por cookie total. Deixamos margem para atributos.
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
     * Obtém e valida um cookie, retornando seu valor descriptografado.
     *
     * @param string $key Nome do cookie.
     * @return mixed Valor do cookie ou null se não existir ou for inválido.
     */
    public function get(string $key): mixed
    {
        $realKey = $this->getRealKey($key);
        $success = false;
        $val = $this->decryptCookie($realKey, $success);

        return $success ? $val : null;
    }

    /**
     * Verifica se um cookie existe na requisição e é íntegro/válido.
     * Se os dados estiverem corrompidos, limpa a chave e retorna false.
     *
     * @param string $key Nome do cookie.
     * @return bool Verdadeiro se o cookie existir e for válido, falso caso contrário.
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
     * Remove um cookie do navegador e da superglobal $_COOKIE.
     *
     * @param string $key Nome do cookie.
     * @param array $options Opções adicionais para o cookie (path, domain, secure, httponly, samesite).
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
     * Limpa todos os cookies gerenciados pelo framework.
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
     * Regenera todos os cookies válidos, recifrando seus valores e preservando
     * o tempo de expiração original restante.
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
     * Gera um identificador único para o conjunto atual de cookies válidos.
     *
     * @return string ID único baseado no conteúdo dos cookies.
     */
    public function getId(): string
    {
        $all = $this->getAll();
        ksort($all);
        return hash('sha256', json_encode($all));
    }

    /**
     * Obtém todos os cookies válidos como um array associativo.
     *
     * @return array Array associativo de todos os cookies válidos.
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
     * Descriptografa e valida a integridade de um cookie.
     *
     * @param string $realKey Chave completa com prefixo.
     * @param bool &$success Indica se a decodificação foi bem-sucedida.
     * @param int &$exp Armazena o timestamp de expiração do cookie.
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

            // Verifica se está no formato envelope com expiração
            if (is_array($data) && array_key_exists('v', $data) && array_key_exists('exp', $data)) {
                $exp = (int) $data['exp'];
                if ($exp > 0 && time() > $exp) {
                    // Cookie expirado internamente (anti-replay)
                    return null;
                }
                $success = true;
                return $data['v'];
            }

            // Retrocompatibilidade com cookies gravados sem envelope
            $success = true;
            return $data;
        } catch (\JsonException $e) {
            Logs::register('Cookies', "JSON decoding failed for cookie '{$realKey}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Informações de depuração da classe.
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
