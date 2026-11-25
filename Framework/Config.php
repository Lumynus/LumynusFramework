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
     * Retrieves settings from config.ini file.
     *
     * @return array|null Returns configuration array or null if file does not exist.
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
     * Sets a configuration in the config.ini file.
     *
     * @param string $section Configuration section.
     * @param string $key Configuration key.
     * @param mixed $value Configuration value.
     * @return bool Returns true if set successfully, false otherwise.
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
     * Removes a setting from config.ini file.
     *
     * @param string $section Configuration section.
     * @param string $key Configuration key.
     * @return bool True if setting was removed successfully, false otherwise.
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
     * Removes an entire section from config.ini file.
     *
     * @param string $section Configuration section to remove.
     * @return bool True if section was removed successfully, false otherwise.
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
     * Retrieves settings from aplication.json file.
     *
     * @return array|null Returns configuration array or null if file does not exist.
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

        // If not found or invalid, return default
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
            ],
            "errors" => [
                "formatter" => null,
                "pages" => []
            ]
        ];
    }

    /**
     * Retrieves current PHP execution environment.
     *
     * @return string Returns a string representing the execution environment.
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
     * Retrieves operating system PHP is running on.
     *
     * @return string Returns a string representing the operating system.
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
     * Retrieves host information for current HTTP request.
     *
     * @param string $part Part to return: host, port, or full.
     *
     * @return string|null Returns host, port, or host with port.
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
     * Retrieves HTTP method of current request.
     *
     * @return string Returns HTTP method (GET, POST, etc.).
     */
    public static function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Checks whether current request originated from localhost.
     *
     * @return bool True if localhost, false otherwise.
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
     * Returns Lumynus project path.
     *
     * @return string Absolute path to project root directory.
     */
    public static function projectPath(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * Returns the mode the application is configured in.
     *
     * @return bool true -> Production, false -> Development
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
     * Sets production or development mode in config.ini file.
     *
     * @param bool $isProduction Sets whether mode is production (true) or development (false).
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
     * Retrieves an instance of the Luma class.
     * @return Luma Instance of the Luma class.
     */
    public function __debugInfo(): array
    {
        return [
            'Lumynus' => "Framework PHP"
        ];
    }
}
