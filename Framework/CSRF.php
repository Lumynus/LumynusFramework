<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;
use Lumynus\Framework\Sessions;
use Lumynus\Framework\Config;

final class CSRF extends LumaClasses
{
    /**
     * @var Sessions Shared session instance.
     */
    private static ?Sessions $session = null;

    /**
     * Returns the singleton session instance.
     *
     * @return Sessions
     */
    private static function session(): Sessions
    {
        if (self::$session === null) {
            self::$session = new Sessions();
        }
        return self::$session;
    }

    /**
     * Generates and stores a CSRF token in the session.
     *
     * @return string The generated CSRF token.
     */
    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        self::session()->set(
            Config::getApplicationConfig()['security']['csrf']['tokenName'],
            $token
        );
        return $token;
    }

    /**
     * Checks whether CSRF token is valid.
     *
     * @param string $token The CSRF token to validate.
     * @return bool True if token is valid, false otherwise.
     */
    public static function isValidToken(string $token): bool
    {
        if (empty($token) || !ctype_xdigit($token) || strlen($token) !== 64) {
            return false;
        }

        $name = Config::getApplicationConfig()['security']['csrf']['tokenName'];

        if (!self::session()->has($name)) {
            return false;
        }

        $sessionToken = self::session()->get($name);
        return hash_equals($sessionToken, $token);
    }

    /**
     * Recupera Token salvo
     *
     * @return string The generated CSRF token.
     */
    public static function getToken(): string|null
    {
        return
            self::session()->get(
                Config::getApplicationConfig()['security']['csrf']['tokenName']
            ) ?? null;
    }
}
