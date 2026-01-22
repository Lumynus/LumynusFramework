<?php

declare(strict_types=1);

namespace Lumynus\Bundle\Framework;

use Lumynus\Bundle\Framework\Config;

final class Cookies extends LumaClasses implements \Lumynus\Bundle\Contracts\CookieInterface
{

    private array $cookieParams = [];
    private string $secretKey;

    /**
     * Constructor to initialize default cookie settings and secret key automatically.
     */
    public function __construct()
    {
        if (!in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            throw new \RuntimeException('AES-256-GCM not supported on this server.');
        }

        $secret = Config::getAplicationConfig()['security']['cookie']['secret'] ?? 'LumynusApp';
        $this->secretKey = $this->generateSecretKey($secret);

        $this->cookieParams = [
            'path' => '/',
            'domain' => Config::getAplicationConfig()['App']['domain'] ?? '',
            'secure' => Config::modeProduction(),
            'httponly' => Config::modeProduction(),
            'samesite' => 'Strict'
        ];
        $this->applyExistingCookies();
    }

    /**
     * Gera a chave secreta baseada no nome da aplicação.
     */
    private function generateSecretKey(string $secret): string
    {
        return  hash_hkdf(
            'sha256',
            $secret,
            32,
            'LumynusCookieEncryption',
            ''
        );
    }


    /**
     * Aplica cookies existentes, validando assinatura e descriptografando.
     */
    private function applyExistingCookies(): void
    {
        foreach ($_COOKIE as $key => $value) {
            $val = $this->get($key);
            if ($val === null) {
                unset($_COOKIE[$key]); // Cookie inválido ou adulterado
            }
        }
    }

    /**
     * Define um cookie com criptografia e assinatura.
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
        $expireTime = $expire > 0 ? time() + $expire : 0;

        $params = array_merge($this->cookieParams, $options);

        $payload = json_encode($value, JSON_THROW_ON_ERROR);

        $iv = random_bytes(12);

        $aad = $key;

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
            throw new \RuntimeException('Falha ao criptografar cookie');
        }

        $cookieValue = base64_encode($iv . $tag . $ciphertext);

        $cookieOptions = $params;
        $cookieOptions['expires'] = $expireTime;

        setcookie($key, $cookieValue, $cookieOptions);

        $_COOKIE[$key] = $cookieValue;
    }

    /**
     * Obtém e valida um cookie, retornando seu valor descriptografado.
     * @param string $key Nome do cookie.
     * @return mixed Valor do cookie ou null se não existir ou for inválido.
     */
    public function get(string $key): mixed
    {
        if (!isset($_COOKIE[$key])) {
            return null;
        }

        $decoded = base64_decode($_COOKIE[$key], true);
        if ($decoded === false || strlen($decoded) < 28) {
            return null;
        }

        $iv         = substr($decoded, 0, 12);
        $tag        = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $aad = $key;

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
            unset($_COOKIE[$key]);
            return null;
        }
        try {
            return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Verifica se um cookie existe e é válido.
     * @param string $key Nome do cookie.
     * @return bool Verdadeiro se o cookie existir e for válido, falso caso contrário.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Remove um cookie.
     * @param string $key Nome do cookie.
     * @param array $options Opções adicionais para o cookie (path, domain, secure, httponly, samesite).
     * @return void
     */
    public function remove(string $key, array $options = []): void
    {
        $params = array_merge($this->cookieParams, $options);

        setcookie($key, '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);

        unset($_COOKIE[$key]);
    }

    /**
     * Limpa todos os cookies.
     * @return void
     */
    public function clear(): void
    {
        foreach ($_COOKIE as $key => $value) {
            $this->remove($key);
        }
    }

    /**
     * Regenera todos os cookies mantendo seus valores.
     * @return void
     */
    public function regenerate(): void
    {
        foreach ($_COOKIE as $key => $value) {
            $val = $this->get($key);
            if ($val !== null) $this->set($key, $val);
        }
    }

    /**
     * Gera um ID único para o conjunto atual de cookies.
     * @return string ID único baseado no conteúdo dos cookies.
     */
    public function getId(): string
    {
        return md5(json_encode($this->getAll()));
    }

    /**
     * Obtém todos os cookies válidos como um array associativo.
     * @return array Array associativo de todos os cookies válidos.
     */
    public function getAll(): array
    {
        $all = [];
        foreach ($_COOKIE as $key => $value) {
            $val = $this->get($key);
            if ($val !== null) $all[$key] = $val;
        }
        return $all;
    }


    public function __debugInfo(): array
    {
        return [
            'Lumynus' => "Framework PHP"
        ];
    }
}
