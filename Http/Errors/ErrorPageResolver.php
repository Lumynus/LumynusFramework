<?php

declare(strict_types=1);

namespace Lumynus\Http\Errors;

use Lumynus\Framework\Config;
use Lumynus\Framework\Logs;
use Lumynus\Framework\Luma;

/**
 * Resolves custom HTML pages for error responses.
 *
 * @package Lumynus\Http\Errors
 */
final class ErrorPageResolver
{
    /**
     * Extensions searched when the configuration does not specify one.
     */
    private const EXTENSIONS = ['luma', 'html'];

    /**
     * Resolves the page configured for the error status.
     *
     * @param ErrorPayload $erro Normalized error data.
     * @return string|null Page HTML or null when no valid page exists.
     */
    public function resolve(ErrorPayload $erro): ?string
    {
        $config = Config::getApplicationConfig();
        $pages = $config['errors']['pages'] ?? [];
        if (!is_array($pages)) {
            return null;
        }

        foreach ([(string) $erro->status, 'default'] as $key) {
            $view = isset($pages[$key]) ? $pages[$key] : null;
            if (!is_string($view) || $view === '') {
                continue;
            }

            $file = $this->locate($view);
            if ($file !== null) {
                return $this->serve($file, $erro);
            }
        }

        return null;
    }

    /**
     * Locates the exact file or its .luma and .html variants.
     *
     * @param string $view Configured view name.
     * @return string|null Safe name of the located view.
     */
    private function locate(string $view): ?string
    {
        $candidates = pathinfo($view, PATHINFO_EXTENSION) !== ''
            ? [$view]
            : array_map(
                static fn (string $extension): string => "{$view}.{$extension}",
                self::EXTENSIONS
            );

        foreach ($candidates as $candidate) {
            if (Luma::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Renders a .luma page or reads static HTML.
     *
     * @param string $file Name of the located view.
     * @param ErrorPayload $erro Normalized error data.
     * @return string|null Served HTML or null on failure.
     */
    private function serve(string $file, ErrorPayload $erro): ?string
    {
        try {
            if (str_ends_with($file, '.html')) {
                $content = Luma::raw($file);
                return trim($content) === '' ? null : $content;
            }

            $content = Luma::render(
                $file,
                [
                    'codigo' => $erro->status,
                    'mensagem' => $erro->message,
                ],
                false,
                true
            );
            return trim($content) === '' ? null : $content;
        } catch (\Throwable $failure) {
            Logs::register(
                'Falha ao servir página de erro',
                [
                    'view' => $file,
                    'erro' => $failure->getMessage(),
                ]
            );
            return null;
        }
    }
}
