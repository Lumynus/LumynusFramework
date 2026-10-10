<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;
use Lumynus\Framework\Config;
use Lumynus\Framework\Logs;

final class Sessions extends LumaClasses implements \Lumynus\Contracts\SessionInterface
{
    private static ?self $activeInstance = null;
    private string $secret;
    private string $key;
    private array $options = [];

    /**
     * Construtor para inicialização e configuração segura da sessão.
     *
     * @param array $userOptions Opções para diretivas de sessão (ex: cookie_lifetime, gc_maxlifetime, cookie_path, cookie_domain, cookie_secure, cookie_samesite, use_strict_mode).
     */
    public function __construct(array $userOptions = [])
    {
        $sessionConfig = Config::getApplicationConfig()['security']['session'] ?? [];
        $this->secret = $sessionConfig['secret'] ?? 'LumynusApp';

        if (Config::productionMode() && ($this->secret === 'LumynusApp' || strlen($this->secret) < 16)) {
            Logs::register('Sessions', 'Warning: Session secret key is insecure or using default in production mode.');
        }

        if (!in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            throw new \RuntimeException('AES-256-GCM not supported on this server.');
        }

        $this->key = hash_hkdf(
            'sha256',
            $this->secret,
            32,
            'LumynusSessionEncryption',
            'LumynusSessionSalt'
        );

        $cookieLifetime = isset($userOptions['cookie_lifetime'])
            ? (int) $userOptions['cookie_lifetime']
            : (int) ($sessionConfig['lifetime'] ?? ini_get('session.cookie_lifetime') ?: 0);

        $gcMaxlifetime = isset($userOptions['gc_maxlifetime'])
            ? (int) $userOptions['gc_maxlifetime']
            : (int) ($sessionConfig['gc_maxlifetime'] ?? max($cookieLifetime, (int) (ini_get('session.gc_maxlifetime') ?: 1440)));

        $defaults = [
            'use_trans_sid'     => 0,
            'use_only_cookies'  => 1,
            'cookie_httponly'   => 1,
            'cookie_lifetime'   => $cookieLifetime,
            'gc_maxlifetime'    => $gcMaxlifetime,
            'cookie_path'       => '/',
            'cookie_domain'     => Config::getApplicationConfig()['App']['domain'] ?? '',
            'cookie_secure'     => Config::productionMode(),
            'cookie_samesite'   => 'Lax',
            'use_strict_mode'   => Config::productionMode(),
        ];

        $this->options = array_merge($defaults, $userOptions);

        if (session_status() === PHP_SESSION_NONE) {
            $file = '';
            $line = 0;
            if (PHP_SAPI !== 'cli' && headers_sent($file, $line)) {
                Logs::register('Sessions', "Cannot start session: headers already sent in {$file}:{$line}");
                if (!Config::productionMode()) {
                    throw new \RuntimeException("Cannot start session: headers already sent in {$file}:{$line}");
                }
            } else {
                session_name(
                    trim(
                        strtoupper(Config::getApplicationConfig()['App']['applicationName'] ?? 'LUMYNUS') . '_SESSID'
                    )
                );

                foreach ($this->options as $key => $value) {
                    ini_set("session.$key", (string) $value);
                }

                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
            }
        }

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
     * Inserir uma chave e valor na sessão.
     *
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
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return isset($_SESSION[$key]) ? $this->decrypt($_SESSION[$key]) : null;
    }

    /**
     * Verifica se uma chave existe na sessão e se seus dados são válidos/íntegros.
     * Se os dados estiverem corrompidos ou adulterados, limpa a chave e retorna false.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (!isset($_SESSION[$key])) {
            return false;
        }

        $success = false;
        $this->decrypt($_SESSION[$key], $success);

        if (!$success) {
            unset($_SESSION[$key]);
            return false;
        }

        return true;
    }

    /**
     * Remove uma chave da sessão.
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Limpa todos os dados da sessão.
     *
     * @return void
     */
    public function clear(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);

        session_destroy();
    }

    /**
     * Regenera o ID da sessão de forma segura, recifrando os dados com o novo ID.
     *
     * @return void
     */
    public function regenerate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session not active');
        }

        $data = $this->getAll();

        session_regenerate_id(true);

        $_SESSION = [];

        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Obtém o ID da sessão atual.
     *
     * @return string
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Obtém todos os dados da sessão.
     *
     * @return array
     */
    public function getAll(): array
    {
        $all = [];
        foreach ($_SESSION as $key => $value) {
            $success = false;
            $decrypted = $this->decrypt($value, $success);
            if ($success) {
                $all[$key] = $decrypted;
            }
        }
        return $all;
    }

    /**
     * Retorna o AAD (Additional Authenticated Data) vinculado ao ID da sessão.
     *
     * @return string
     */
    private function getAad(): string
    {
        $id = session_id();
        return hash('sha256', ($id !== '' ? $id : session_name()));
    }

    /**
     * Criptografa um valor usando AES-256-GCM vinculado ao AAD da sessão.
     *
     * @param mixed $value
     * @return string
     */
    private function encrypt(mixed $value): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session not active');
        }

        $payload = json_encode($value, JSON_THROW_ON_ERROR);

        $iv = random_bytes(12);

        $ciphertext = openssl_encrypt(
            $payload,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $this->getAad()
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Failed to encrypt session data');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Descriptografa e valida a integridade de um valor da sessão.
     *
     * @param mixed $data
     * @param bool &$success Indica se a descriptografia e decodificação JSON foram bem-sucedidas.
     * @return mixed
     */
    private function decrypt(mixed $data, bool &$success = false): mixed
    {
        $success = false;

        if (!is_string($data)) {
            return null;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            Logs::register('Sessions', 'Session not active');
            return null;
        }

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
            $this->getAad()
        );

        if ($plain === false) {
            Logs::register('Sessions', 'Integrity check failed: possible tampering or session ID mismatch.');
            return null;
        }

        try {
            $result = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
            $success = true;
            return $result;
        } catch (\JsonException $e) {
            Logs::register('Sessions', 'JSON decoding failed: ' . $e->getMessage());
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
