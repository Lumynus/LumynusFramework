<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;

final class Config extends LumaClasses
{

    /**
     * Obtém as configurações do arquivo config.ini.
     *
     * @return array|null Retorna um array com as configurações ou null se o arquivo não existir.
     */
    public static function getINI(): ?array
    {
        $file = self::projectPath() . DIRECTORY_SEPARATOR . 'config.ini';
        if (!file_exists($file)) {
            return null;
        }
        $config =  parse_ini_file($file, true);
        return $config ?? null;
    }

    /**
     * Define uma configuração no arquivo config.ini.
     *
     * @param string $section Seção da configuração.
     * @param string $key Chave da configuração.
     * @param mixed $value Valor da configuração.
     * @return bool Retorna true se a configuração foi definida com sucesso, caso contrário false.
     */
    public static function setINI(string $section, string $key, mixed $value): bool
    {
        $file = self::projectPath() . DIRECTORY_SEPARATOR . 'config.ini';

        if (!file_exists($file)) {
            return false;
        }

        if($section === 'app') {
            throw new \RuntimeException("The 'mode' key in the 'app' section cannot be modified directly. Use setProductionMode() instead.");
        }

        $config = parse_ini_file($file, true);

        if ($config === false) {
            return false;
        }

        $config[$section][$key] = $value;

        $iniContent = '';

        foreach ($config as $sectionName => $values) {
            $iniContent .= "[$sectionName]\n";

            foreach ($values as $key => $value) {
                $iniContent .= "$key = " . (is_string($value) ? '"' . $value . '"' : $value) . "\n";
            }

            $iniContent .= "\n";
        }

        return file_put_contents($file, $iniContent) !== false;
    }

    /**
     * Remove uma configuração do arquivo config.ini.
     *
     * @param string $section Seção da configuração.
     * @param string $key Chave da configuração.
     * @return bool Retorna true se a configuração foi removida com sucesso, caso contrário false.
     */
    public static function removeINI(string $section, string $key): bool
    {
        $file = self::projectPath() . DIRECTORY_SEPARATOR . 'config.ini';

        if (!file_exists($file)) {
            return false;
        }

        if($section === 'app') {
            throw new \RuntimeException("The 'app' section cannot be removed from the config.ini file.");
        }

        $config = parse_ini_file($file, true);

        if ($config === false || !isset($config[$section][$key])) {
            return false;
        }

        unset($config[$section][$key]);

        $iniContent = '';

        foreach ($config as $sectionName => $values) {
            $iniContent .= "[$sectionName]\n";

            foreach ($values as $key => $value) {
                $value = is_string($value) ? '"' . $value . '"' : $value;
                $iniContent .= "$key = $value\n";
            }

            $iniContent .= "\n";
        }

        return file_put_contents($file, $iniContent) !== false;
    }

    /**
     * Remove uma seção inteira do arquivo config.ini.
     *
     * @param string $section Seção da configuração a ser removida.
     * @return bool Retorna true se a seção foi removida com sucesso, caso contrário false.
     */
    public static function removeINISection(string $section): bool
    {
        $file = self::projectPath() . DIRECTORY_SEPARATOR . 'config.ini';

        if (!file_exists($file)) {
            return false;
        }

        if($section === 'app') {
            throw new \RuntimeException("The 'app' section cannot be removed from the config.ini file.");
        }

        $config = parse_ini_file($file, true);

        if ($config === false || !isset($config[$section])) {
            return false;
        }

        unset($config[$section]);

        $iniContent = '';

        foreach ($config as $sectionName => $values) {
            $iniContent .= "[$sectionName]\n";

            foreach ($values as $key => $value) {
                $value = is_string($value) ? '"' . $value . '"' : $value;
                $iniContent .= "$key = $value\n";
            }

            $iniContent .= "\n";
        }

        return file_put_contents($file, $iniContent) !== false;
    }

    /**
     * Obtém as configurações do arquivo aplication.json.
     *
     * @return array|null Retorna um array com as configurações ou null se o arquivo não existir.
     */
    public static function getApplicationConfig(): ?array
    {
        $file = self::projectPath() . DIRECTORY_SEPARATOR . 'application.json';

        if (file_exists($file)) {
            $config = json_decode(file_get_contents($file), true);

            if (is_array($config) && isset($config[0])) {
                return $config[0];
            }
        }

        // Se não existir ou estiver inválido, retorna padrão
        return [
            "App" => [
                "applicationName" => "Lumynus",
                "version" => "1",
                "description" => "A simple PHP framework for building web applications.",
                "author" => "Weleny Santos",
                "email" => "",
                "host" => "",
                "domain" => ""
            ],
            "path" => [
                "public" => "/public/",
                "js" => "/resources/js/",
                "css" => "/resources/css/",
                "views" => "/src/views/",
                "routers" => "/src/routers/",
                "cache" => "/storage/cache/",
                "files" => "/storage/files/"
            ],
            "security" => [
                "csrf" => [
                    "enabled" => true,
                    "tokenName" => "luma_csrf"
                ],
                "assetsIntegrity" => [
                    "enabled" => true
                ],
                "session" =>  [
                    "secret" => "2025_trx$#@@lum@nysCryptSessionsDates"
                ],
                "cookie" => [
                    "secret" => "2025_trx$#@@lum@nysCryptCookiesDates"
                ],
                "cors" => [
                    "enabled" => false,
                    "allowedOrigins" => ["*"],
                    "allowedHeaders" => [],
                    "credentials" => true,
                    "allowedMethods" => [
                        "GET",
                        "POST",
                        "PUT",
                        "DELETE",
                        "OPTIONS",
                        "PATCH",
                    ],
                    "cacheTime" => 86400,
                ],
            ],
            "frontend" => [
                "assetsVersion" => true
            ],
            "database" => [
                "autoClose" => true
            ],
            "logs" => [
                "autoClear" => true
            ],
            "persistentRuntime" => [
                "is" => false
            ]
        ];
    }

    /**
     * Obtém o ambiente de execução atual do PHP.
     *
     * @return string Retorna uma string representando o ambiente de execução.
     */
    public static function getRuntimeEnvironment(): string
    {
        return match (PHP_SAPI) {
            'cli'            => 'cli',
            'cli-server'     => 'php-server',
            'apache2handler' => 'apache',
            'fpm-fcgi'       => 'php-fpm',
            'cgi-fcgi'       => 'cgi',
            'cgi'            => 'cgi',
            'litespeed'      => 'litespeed',
            default           => 'unknown',
        };
    }

    /**
     * Obtém o sistema operacional em que o PHP está sendo executado.
     *
     * @return string Retorna uma string representando o sistema operacional.
     */
    public static function getOperatingSystem(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'Windows',
            'Linux'   => 'Linux',
            'Darwin'  => 'macOS',
            'BSD'     => 'BSD',
            default   => 'Unknown',
        };
    }

    /**
     * Obtém informações sobre o host da requisição HTTP atual.
     *
     * @param string $part Parte a ser retornada: host, port ou full.
     *
     * @return string|null Retorna o host, a porta ou host com porta.
     */
    public static function getHost(string $part = 'host'): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;

        if ($host === null) {
            return null;
        }

        [$hostname, $port] = array_pad(explode(':', $host, 2), 2, null);

        return match ($part) {
            'host' => $hostname,
            'port' => $port,
            'full' => $host,
            default => null,
        };
    }

    /**
     * Obtém o método HTTP da requisição atual.
     *
     * @return string Retorna o método HTTP (GET, POST, etc.).
     */
    public static function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Verifica se a requisição atual é feita a partir de localhost.
     *
     * @return bool Retorna true se for localhost, caso contrário false.
     */
    public static function isLocalhost(): bool
    {
        $host = self::getHost('host');

        if ($host === null) {
            return false;
        }

        return in_array($host, [
            'localhost',
            'localhost.localdomain',
            '127.0.0.1',
            '::1',
        ], true);
    }

    /**
     * Retorna o caminho do projeto Lumynus.
     *
     * @return string Caminho absoluto do diretório raiz do projeto.
     */
    public static function projectPath(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * Retorna o modo em que a Aplicação está configurada
     *
     * @return bool true -> Produção, false -> Desenvolvimento
     */
    public static function productionMode(): bool
    {
        $config = self::getINI();
        if (isset($config['app']['mode']) && $config['app']['mode'] !== 'development') {
            return true;
        }
        return false;
    }

    /**
     * Define o modo de produção ou desenvolvimento no arquivo config.ini.
     *
     * @param bool $isProduction Define se o modo é produção (true) ou desenvolvimento (false).
     * @return void
     */
    public static function setProductionMode(bool $isProduction): void
    {
        $file = self::projectPath() . DIRECTORY_SEPARATOR . 'config.ini';
        $config = self::getINI() ?? [];

        if (!isset($config['app'])) {
            $config['app'] = [];
        }

        $config['app']['mode'] = $isProduction ? 'production' : 'development';
        $config['app']['debug'] = $isProduction ? 'false' : 'true';

        $iniContent = '';
        foreach ($config as $section => $values) {
            $iniContent .= "[$section]\n";
            foreach ($values as $key => $value) {
                $iniContent .= "$key = $value\n";
            }
            $iniContent .= "\n";
        }

        file_put_contents($file, $iniContent);
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
