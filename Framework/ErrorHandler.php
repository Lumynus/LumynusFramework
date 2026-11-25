<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;
use Lumynus\Templates\DebugErrorPage;
use Lumynus\Http\Errors\ErrorFormat;
use Lumynus\Http\Errors\ErrorPayload;
use Lumynus\Http\Errors\ErrorResponder;

class ErrorHandler extends LumaClasses
{
    /**
     * Registers error and exception handlers for Lumynus framework.
     *
     * @return void
     */
    public static function register(callable $callback): void
    {
        $fileConfigured = true;
        $configFile = Config::getINI();
        if (!$configFile || !isset($configFile['app']['debug'])) {
            $fileConfigured = false;
            $callback($fileConfigured);
            print('Application is not in debug mode. Error handler is not registered. Configure your config.ini file to enable debug mode.');
            return;
        }

        $callback($fileConfigured);

        /**
         * Renders a normalized response for uncaught exceptions.
         *
         * @param ErrorPayload $payload Normalized error data.
         * @param bool $debug Indicates whether debug mode is active.
         * @return never
         */
        $renderError = function (ErrorPayload $payload, bool $debug): never {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            if (!$debug) {
                self::logUnhandledError($payload);
            }

            if (headers_sent($filename, $linenum)) {
                Logs::register("Headers already sent in $filename on line $linenum. Cannot set HTTP status code or content type.", 'error');
            }

            $format = ErrorFormat::negotiate($_SERVER) === ErrorFormat::Json
                ? ErrorFormat::Json
                : ErrorFormat::Html;

            if ($format === ErrorFormat::Json) {
                if ($debug) {
                    $response = new \Lumynus\Http\HttpResponse();
                    $response
                        ->status($payload->status)
                        ->header('Content-Type', 'application/json; charset=utf-8')
                        ->send(json_encode(
                            $payload->debug,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        ))
                        ->dispatch();
                } else {
                    ErrorResponder::fromConfig()
                        ->respond(
                            new ErrorPayload($payload->status, 'Internal Server Error'),
                            'json',
                            false
                        )
                        ->dispatch();
                }
            } else {
                if ($debug) {
                    $response = new \Lumynus\Http\HttpResponse();
                    $response
                        ->status($payload->status)
                        ->html((new DebugErrorPage())->render($payload->debug))
                        ->dispatch();
                } else {
                    ErrorResponder::fromConfig()
                        ->respond(
                            new ErrorPayload($payload->status, 'Internal Server Error'),
                            'html'
                        )
                        ->dispatch();
                }
            }
            exit;
        };

        set_exception_handler(function ($e) use ($configFile, $renderError) {
            $renderError(
                ErrorPayload::fromThrowable($e),
                self::debugActive($configFile)
            );
        });

        set_error_handler(function ($severity, $message, $file, $line) use ($configFile, $renderError) {
            $renderError(
                ErrorPayload::fromPhpError($severity, $message, $file, $line),
                self::debugActive($configFile)
            );
        });

        register_shutdown_function(function () use ($configFile, $renderError) {
            $error = error_get_last();

            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                $renderError(
                    ErrorPayload::fromFatal($error),
                    self::debugActive($configFile)
                );
            }
        });
    }

    /**
     * Checks whether debug mode is enabled in config.ini.
     *
     * @param array<string, mixed> $ini Configuration loaded from config.ini.
     * @return bool True when debug mode is active.
     */
    private static function debugActive(array $ini): bool
    {
        $debug = $ini['app']['debug'] ?? null;
        return $debug === 'true' || $debug == '1';
    }

    /**
     * Records the original error details before the public response is sanitized.
     *
     * @param ErrorPayload $payload Normalized error data.
     * @return void
     */
    private static function logUnhandledError(ErrorPayload $payload): void
    {
        Logs::register('Unhandled application error', [
            'status' => $payload->status,
            'message' => $payload->message,
            'debug' => $payload->debug,
        ]);
    }

    /**
     * Class debug information.
     */
    public function __debugInfo(): array
    {
        return [
            'Lumynus' => "Framework PHP"
        ];
    }
}
