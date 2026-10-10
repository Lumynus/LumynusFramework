<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;
use Lumynus\Templates\Errors;
use Lumynus\Framework\Config;

final class CORS extends LumaClasses
{

    use Errors;

    /**
     * Allowed origins for CORS.
     * These are origins that the server accepts for CORS requests.
     * You can add or remove origins as needed.
     */
    private $allowedOrigins = [];

    /**
     * Allowed methods for CORS.
     * These are HTTP methods that the server accepts from CORS requests.
     * You can add or remove methods as needed.
     */
    private $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'];

    /**
     * Allowed headers for CORS.
     * These are headers that the server accepts from CORS requests.
     * You can add or remove headers as needed.
     */
       private $allowedHeaders = [
        'Content-Type',
        'Authorization',
        'Accept',
        'Origin',
        'X-Requested-With',
        'X-CSRF-Token',
        'HTTP-XSRF-TOKEN',
        'luma_csrf',
        'LUMA-CSRF-TOKEN',
        'LUMA-CSRF',
        'LUMA_CSRF',
        'X-Auth-Token',
        'X-Access-Token',
        'Cache-Control',
        'Pragma',
        'XSRF-TOKEN',
        'CSRF-TOKEN'
    ];

    /**
     * Cache duration for CORS responses.
     * This is time in seconds that the browser should cache CORS responses.
     * Default is 86400 seconds (1 day).
     */
    private $maxAge = 86400; // 1 dia

    /**
     * CORS class constructor.
     * Sets allowed origins, methods, headers and cache duration.
     *
     * @param array $origins Array of allowed origins.
     */
    public function setOrigins(string|array $origins): void
    {
        $origins = is_array($origins) ? $origins : [$origins];

        $this->allowedOrigins = array_unique(
            array_merge($this->allowedOrigins, $origins)
        );
    }

    /**
     * Sets allowed methods for CORS.
     * @param array $methods Array of allowed HTTP methods.
     * @throws \InvalidArgumentException If methods are invalid.
     */
    public function setMethods(array $methods)
    {
        $this->allowedMethods = $methods;
    }

    /**
     * Sets allowed headers for CORS.
     * @param array $headers Array of allowed headers.
     * @throws \InvalidArgumentException If headers are invalid.
     */
     public function setHeaders(array $headers)
    {
         $this->allowedHeaders = array_unique(
            array_merge($this->allowedHeaders, $headers)
        );
    }

    /**
     * Sets cache duration for CORS responses.
     * @param int $seconds Duration in seconds.
     * @throws \InvalidArgumentException If duration is not a positive integer.
     */
    public function setCacheTime(int $seconds)
    {
        if ($seconds > 0) {
            $this->maxAge = $seconds;
        } else {
            throw new \InvalidArgumentException('Cache time must be a positive integer.');
        }
    }

    /**
     * Handles CORS request.
     * Checks if origin is allowed and applies CORS headers.
     * If origin is not allowed, returns a 403 error.
     *
     * @return void
     */
    public function handle()
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (!in_array($origin, $this->allowedOrigins) && !in_array('*', $this->allowedOrigins)) {
            Logs::register('CORS Error', "Blocked origin: '{$origin}'");
            self::throwError('CORS origin not allowed', 403);
            return;
        }
        
        if (in_array('*', $this->allowedOrigins) && empty($origin)) {
            $origin = '*';
        }

        header('Access-Control-Allow-Origin: ' . $origin);

        $corsCfg = Config::getApplicationConfig()['security']['cors'] ?? [];
        if (!empty($corsCfg['enabled'])) {
            if (isset($corsCfg['credentials'])) {
                header(
                    'Access-Control-Allow-Credentials: ' .
                        ($corsCfg['credentials'] === true ? 'true' : 'false')
                );
            }
        }

        header('Vary: Origin');
        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
        header('Access-Control-Max-Age: ' . $this->maxAge);

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
    }
}
