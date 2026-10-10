<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;

final class HttpClient extends LumaClasses
{

    /**
     * Executes a simple HTTP request.
     *
     * @param string $url URL for the request.
     * @param string $metodo HTTP method (GET, POST, PUT, DELETE). Default: "GET".
     * @param array $headers HTTP headers for the request.
     * @param array|string $body Request body, can be an array or string.
     *
     * @return array Returns associative array containing:
     *               - 'status_code' (int): HTTP response status code.
     *               - 'response' (string): Response body or error message.
     */
    public static function request(string $url, string $metodo = "GET", array $headers = [], array|string $body = "", $sslVerify = true): array
    {
        $metodos = ["GET", "POST", "PUT", "DELETE"];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ["status_code" => 400, "response" => "URL inválida -> " . $url];
        }

        if (!in_array($metodo, $metodos)) {
            return ["status_code" => 400, "response" => "Método não permitido -> " . $metodo];
        }

        if (!function_exists('curl_version')) {
            return ["status_code" => 500, "response" => "A lib cURL não está habilitada. Verifique seu php.ini"];
        }

        // Detect request body format
        $urlEncode = false;
        $jsonEncode = false;
        $xmlEncode = false;

        foreach ($headers as $header) {
            if (stripos($header, "application/x-www-form-urlencoded") !== false) $urlEncode = true;
            if (stripos($header, "application/json") !== false) $jsonEncode = true;
            if (stripos($header, "application/xml") !== false) $xmlEncode = true;
        }

        // Convert body according to type
        if ($urlEncode && is_array($body)) $body = http_build_query($body);
        if ($jsonEncode && is_array($body)) $body = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($xmlEncode && (!is_string($body) || stripos($body, "<?xml") === false)) {
            return ["status_code" => 400, "response" => "Body inválido para application/xml"];
        }

        // cURL configuration
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($metodo !== "GET") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        if (PHP_VERSION_ID < 80500) {
            curl_close($ch);
        } else {
            unset($ch);
        }

        if ($curlError) {
            return ["status_code" => 500, "response" => "Erro na requisição -> " . $curlError];
        }

        return ["status_code" => $httpCode, "response" => $response];
    }
}
