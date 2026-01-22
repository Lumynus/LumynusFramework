<?php

declare(strict_types=1);

namespace Lumynus\Bundle\Framework;

use Lumynus\Bundle\Framework\LumaClasses;
use Lumynus\Bundle\Framework\Config;

final class Sessions extends LumaClasses implements \Lumynus\Bundle\Contracts\SessionInterface
{

    private string $secret;
    private string $key;
    private array $options = [];


    /**
     * Constructor to initialize the session.
     * @param array $userOptions Opções personalizadas para a sessão.
     */
    public function __construct(array $userOptions = [])
    {

        $this->secret = Config::getAplicationConfig()['security']['session']['secret'] ?? 'LumynusApp';

        if (!in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            throw new \RuntimeException('AES-256-GCM not supported on this server.');
        }

        $this->key = hash('sha256', $this->secret, true);

        $defaults = [
            'use_trans_sid'     => 0,
            'use_only_cookies'  => 1,
            'cookie_httponly'   => 1,
            'cookie_lifetime'   => 0,
            'cookie_path'       => '/',
            'cookie_domain'     => Config::getAplicationConfig()['App']['domain'] ?? '',
            'cookie_secure'     => Config::modeProduction(),
            'cookie_samesite'   => 'Lax',
            'use_strict_mode'   => Config::modeProduction(),
        ];

        $this->options = array_merge($defaults, $userOptions);

        if (session_status() === PHP_SESSION_NONE) {

            session_name('LumynusSession_' . Config::getAplicationConfig()['App']['nameApplication']);
            session_start($this->options);
        }
    }


    /**
     * Inserir uma chave e valor na sessão.
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $this->encrypt($value);
    }

    /**
     * Recuperar o valor de uma chave na sessão.
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return isset($_SESSION[$key]) ? $this->decrypt($_SESSION[$key]) : null;
    }

    /**
     * Verifica se uma chave existe na sessão.
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove uma chave da sessão.
     * @param string $key
     * @return void
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Limpa todos os dados da sessão.
     * @return void
     */
    public function clear(): void
    {
        $_SESSION = [];
        setcookie(session_name(), '', time() - 3600, '/');
        session_destroy();
    }

    /**
     * Regenera o ID da sessão.
     * @return void
     */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Obtém o ID da sessão atual.
     * @return string
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Obtém todos os dados da sessão.
     * @return array
     */
    public function getAll(): array
    {
        $all = [];
        foreach ($_SESSION as $key => $value) {
            $all[$key] = $this->decrypt($value);
        }
        return $all;
    }

    /**
     * Criptografa um valor usando AES-256-CBC
     */
    private function encrypt(mixed $value): string
    {
        $payload = json_encode($value, JSON_THROW_ON_ERROR);

        $iv = random_bytes(12);

        $ciphertext = openssl_encrypt(
            $payload,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            session_id()
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Failed to encrypt session data');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }



    /**
     * Descriptografa um valor
     */
    private function decrypt(string $data): mixed
    {
        $decoded = base64_decode($data, true);
        if ($decoded === false || strlen($decoded) < 28) {
            return null;
        }

        $iv         = substr($decoded, 0, 12);
        $tag        = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $plain = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            session_id()
        );

        return $plain !== false
            ? json_decode($plain, true, 512, JSON_THROW_ON_ERROR)
            : null;
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
