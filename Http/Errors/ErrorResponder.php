<?php

declare(strict_types=1);

namespace Lumynus\Http\Errors;

use Lumynus\Contracts\ErrorFormatter;
use Lumynus\Framework\Config;
use Lumynus\Framework\Logs;
use Lumynus\Http\HttpResponse;

/**
 * Builds centralized HTTP error responses.
 *
 * @package Lumynus\Http\Errors
 */
final class ErrorResponder
{
    /**
     * Creates an error response resolver.
     *
     * @param ErrorFormatter $formatter Active error formatter.
     * @param ErrorPageResolver $pages HTML page resolver.
     */
    public function __construct(
        private readonly ErrorFormatter $formatter,
        private readonly ErrorPageResolver $pages,
    ) {}

    /**
     * Creates the active responder from the application configuration.
     *
     * @return self Configured responder or the default responder.
     */
    public static function fromConfig(): self
    {
        $formatter = new DefaultErrorFormatter();
        $configured = Config::getApplicationConfig()['errors']['formatter'] ?? null;

        if (is_string($configured) && $configured !== '') {
            try {
                if (!class_exists($configured)) {
                    throw new \RuntimeException("Error formatter '{$configured}' was not found.");
                }

                $instance = new $configured();
                if (!$instance instanceof ErrorFormatter) {
                    throw new \RuntimeException("Error formatter '{$configured}' does not implement ErrorFormatter.");
                }
                $formatter = $instance;
            } catch (\Throwable $failure) {
                Logs::register('Invalid application error formatter', $failure->getMessage());
            }
        }

        return new self($formatter, new ErrorPageResolver());
    }

    /**
     * Builds an error response without sending it or terminating the process.
     *
     * @param ErrorPayload $error Normalized error data.
     * @param string|null $forcedFormat Explicitly requested format.
     * @param bool $productionRule Indicates whether the production rule should be applied.
     * @return HttpResponse Prepared HTTP response.
     */
    public function respond(
        ErrorPayload $error,
        ?string $forcedFormat = null,
        bool $productionRule = true
    ): HttpResponse {
        if ($productionRule) {
            $error = $this->applyProductionRule($error);
        }

        $format = ErrorFormat::negotiate($_SERVER, $forcedFormat);
        $response = (new HttpResponse())->status($error->status);

        if ($format === ErrorFormat::Html) {
            while (ob_get_level()) {
                ob_end_clean();
            }
        }

        return match ($format) {
            ErrorFormat::Json => $response->json($this->formatter->body($error)),
            ErrorFormat::Xml => $this->xml($response, $error),
            ErrorFormat::JavaScript => $this->javaScript($response, $error),
            ErrorFormat::Text => $this->text($response, $error),
            ErrorFormat::Html => $response->html(
                $this->pages->resolve($error)
                ?? $this->formatter->html($error)
                ?? $this->defaultHtml($error)
            ),
        };
    }

    /**
     * Applies the current production rule: logs the error and replaces the public message.
     *
     * @param ErrorPayload $error Original error data.
     * @return ErrorPayload Data with the adjusted public message.
     */
    private function applyProductionRule(ErrorPayload $error): ErrorPayload
    {
        if (!Config::productionMode()) {
            return $error;
        }

        Logs::register(
            'Application error',
            'Error ' . $error->status . ': ' . (
                is_array($error->message)
                    ? implode('; ', $error->message)
                    : $error->message
            )
        );

        return new ErrorPayload(
            $error->status,
            'Occurred an error in application',
            $error->debug
        );
    }

    /**
     * Builds the framework's XML body.
     *
     * @param HttpResponse $response HTTP response.
     * @param ErrorPayload $error Error data.
     * @return HttpResponse Configured response.
     */
    private function xml(HttpResponse $response, ErrorPayload $error): HttpResponse
    {
        $body = $this->formatter->body($error);
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<error>\n";
        $xml .= "  <code>{$error->status}</code>\n";
        if ($this->formatter instanceof DefaultErrorFormatter) {
            $message = is_array($error->message)
                ? implode('; ', $error->message)
                : $error->message;
            $xml .= '  <message>' . htmlspecialchars($message) . "</message>\n";
        } else {
            foreach ($body as $key => $value) {
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $key) ?? 'value';
                $text = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE)
                    : (string) $value;
                $xml .= "  <{$name}>" . htmlspecialchars($text ?: '') . "</{$name}>\n";
            }
        }
        $xml .= '</error>';

        return $response
            ->header('Content-Type', 'application/xml')
            ->send($xml);
    }

    /**
     * Builds the framework's JavaScript body.
     *
     * @param HttpResponse $response HTTP response.
     * @param ErrorPayload $error Error data.
     * @return HttpResponse Configured response.
     */
    private function javaScript(HttpResponse $response, ErrorPayload $error): HttpResponse
    {
        $body = $this->formatter instanceof DefaultErrorFormatter && is_array($error->message)
            ? $error->message
            : $this->formatter->body($error);
        $body = json_encode($body, JSON_PRETTY_PRINT);
        return $response
            ->header('Content-Type', 'application/javascript')
            ->send('console.error(' . $body . ');');
    }

    /**
     * Builds the framework's text body.
     *
     * @param HttpResponse $response HTTP response.
     * @param ErrorPayload $error Error data.
     * @return HttpResponse Configured response.
     */
    private function text(HttpResponse $response, ErrorPayload $error): HttpResponse
    {
        $body = $this->formatter->body($error);
        $message = $body['error'] ?? $body['mensagem'] ?? $error->message;
        $message = is_array($message)
            ? print_r($message, true)
            : (string) $message;
        return $response->text("Erro {$error->status}: {$message}");
    }

    /**
     * Builds the default HTML page used by the framework.
     *
     * @param ErrorPayload $error Error data.
     * @return string Default HTML.
     */
    private function defaultHtml(ErrorPayload $error): string
    {
        $message = htmlspecialchars(
            is_array($error->message)
                ? implode('<br>', $error->message)
                : $error->message
        );
        $code = $error->status;

        return "<!DOCTYPE html>
    <html lang=\"pt-BR\">
    <head>
        <meta charset=\"UTF-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <title>Erro $code</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background-color: #f8f9fa;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .error-card {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                padding: 40px;
                max-width: 500px;
                width: 100%;
                text-align: left;
                position: relative;
            }

            .error-header {
                display: flex;
                align-items: center;
                margin-bottom: 24px;
            }

            .error-code {
                background-color: #722f37;
                color: white;
                font-size: 24px;
                font-weight: 700;
                padding: 12px 20px;
                border-radius: 8px;
                margin-right: 16px;
                min-width: 80px;
                text-align: center;
            }

            .error-title {
                color: #2c3e50;
                font-size: 20px;
                font-weight: 600;
                margin: 0;
            }

            .error-message {
                color: #5a6c7d;
                font-size: 16px;
                line-height: 1.6;
                margin-top: 16px;
            }

            .framework-signature {
                position: absolute;
                bottom: 16px;
                right: 20px;
                color: #95a5a6;
                font-size: 11px;
                font-weight: 300;
                letter-spacing: 0.5px;
            }

            @media (max-width: 480px) {
                .error-card {
                    padding: 24px;
                    margin: 16px;
                }

                .error-header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .error-code {
                    margin-right: 0;
                    margin-bottom: 12px;
                }
            }
        </style>
    </head>
    <body>
        <div class=\"error-card\">
            <div class=\"error-header\">
                <div class=\"error-code\">$code</div>
                <h1 class=\"error-title\">Oops! Something went wrong</h1>
            </div>
            <div class=\"error-message\">$message</div>
            <div class=\"framework-signature\">Lumynus</div>
        </div>
    </body>
    </html>";
    }
}
