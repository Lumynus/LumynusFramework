<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumaClasses;

/**
 * Manages HTTP requests with authentication and rate limiting support.
 *
 * @author Lumynus Framework
 * @version 2.0
 */
final class LumaHTTP extends LumaClasses
{
    /** @var array<string> List of HTTP headers */
    private array $headers = [];

    /** @var int HTTP status code of the last request */
    private int $statusCode = 0;

    /** @var string Response body of the last request */
    private string $response = '';

    /** @var string Error message of the last request */
    private string $error = '';

    /** @var string URL of the last request */
    private string $lastUrl = '';

    /** @var bool Indicates whether SSL should be verified */
    private bool $verifySSL = true;

    /** @var int Connection timeout in seconds */
    private int $timeout = 30;

    // Rate limiting
    /** @var int|null Request limit per period */
    private ?int $rateLimit = null;

    /** @var int|null Rate limiting period in seconds */
    private ?int $ratePeriod = null;

    /** @var int Request count in current period */
    private int $requestCount = 0;

    /** @var int Timestamp of the current period start */
    private int $periodStart;

    /** @var string Rate limiting strategy */
    private string $rateLimitStrategy = 'wait'; // 'wait', 'throw', 'skip'

    /**
     * Class constructor
     *
     * @throws \RuntimeException If cURL extension is not available
     */
    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('Extensão cURL não está disponível');
        }

        $this->periodStart = time();
    }

    /**
     * Configures Bearer token authentication
     *
     * @param string $token Authentication token
     * @param string $contentType Content type (json, xml, form, text)
     * @param string $accept Expected response type
     * @return self
     */
    public function useBearer(string $token, string $contentType = 'json', string $accept = 'json'): self
    {
        $this->headers = array_merge($this->headers, [
            'Authorization: Bearer ' . $token,
            $this->getContentTypeHeader($contentType),
            $this->getAcceptHeader($accept)
        ]);

        return $this;
    }

    /**
     * Configures API Key authentication
     *
     * @param string $apiKey API Key string
     * @param string $contentType Content type
     * @param string $accept Expected response type
     * @return self
     */
    public function useApiKey(string $apiKey, string $contentType = 'json', string $accept = 'json'): self
    {
        $this->headers = array_merge($this->headers, [
            'X-API-Key: ' . $apiKey,
            $this->getContentTypeHeader($contentType),
            $this->getAcceptHeader($accept)
        ]);

        return $this;
    }

    /**
     * Configures HTTP Basic authentication
     *
     * @param string $username Username
     * @param string $password Password
     * @param string $contentType Content type
     * @param string $accept Expected response type
     * @return self
     */
    public function useBasicAuth(string $username, string $password, string $contentType = 'json', string $accept = 'json'): self
    {
        $credentials = base64_encode($username . ':' . $password);

        $this->headers = array_merge($this->headers, [
            'Authorization: Basic ' . $credentials,
            $this->getContentTypeHeader($contentType),
            $this->getAcceptHeader($accept)
        ]);

        return $this;
    }

    /**
     * Adds custom HTTP headers
     *
     * @param array<string, string> $headers Headers in ['key' => 'value'] format
     * @return self
     */
    public function headers(array $headers): self
    {
        foreach ($headers as $key => $value) {
            $this->headers[] = $key . ': ' . $value;
        }

        return $this;
    }

    /**
     * Configures request timeout
     *
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    /**
     * Configures SSL certificate verification
     *
     * @param bool $verify Whether to verify SSL certificate
     * @return self
     */
    public function sslVerification(bool $verify = true): self
    {
        $this->verifySSL = $verify;
        return $this;
    }

    /**
     * Configures rate limiting.
     *
     * @param int $requests Maximum number of requests
     * @param int $seconds Period duration in seconds
     * @param string $strategy Rate limiting strategy ('wait', 'throw', 'skip')
     * @return self
     */
    public function rateLimit(int $requests, int $seconds, string $strategy = 'wait'): self
    {
        $this->rateLimit = max(1, $requests);
        $this->ratePeriod = max(1, $seconds);

        $validStrategies = ['wait', 'throw', 'skip'];
        $this->rateLimitStrategy = in_array($strategy, $validStrategies) ? $strategy : 'wait';

        return $this;
    }

    /**
     * Performs a GET request.
     *
     * @param string $url Request URL
     * @return self
     */
    public function get(string $url): self
    {
        return $this->request('GET', $url);
    }

    /**
     * Performs a POST request.
     *
     * @param string $url Request URL
     * @param mixed $data Payload data to send
     * @return self
     */
    public function post(string $url, $data = null): self
    {
        return $this->request('POST', $url, $data);
    }

    /**
     * Performs a PUT request.
     *
     * @param string $url Request URL
     * @param mixed $data Payload data to send
     * @return self
     */
    public function put(string $url, $data = null): self
    {
        return $this->request('PUT', $url, $data);
    }

    /**
     * Performs a DELETE request.
     *
     * @param string $url Request URL
     * @return self
     */
    public function delete(string $url): self
    {
        return $this->request('DELETE', $url);
    }

    /**
     * Performs the HTTP request.
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param mixed $data Payload data to send
     * @return self
     * @throws \InvalidArgumentException On invalid URLs or methods
     */
    public function request(string $method, string $url, $data = null): self
    {
        // Robust validation checks
        $this->validateUrl($url);
        $this->validateMethod($method);
        $this->validateRequestData($data, $method);
        $this->validateConfiguration();

        // Apply rate limiting (may return false to skip)
        if (!$this->applyRateLimit()) {
            return $this; // Request skipped due to rate limit
        }

        $this->lastUrl = $url;

        try {
            $curl = $this->createCurlHandle($url, $method, $data);

            $response = curl_exec($curl);
            $this->statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);

            if (PHP_VERSION_ID < 80500) {
                curl_close($curl);
            } else {
                unset($curl);
            }

            if ($response === false) {
                $this->error = $curlError ?: 'Unknown cURL request failure';
                $this->response = '';
                throw new \RuntimeException($this->error);
            }

            $this->response = (string) $response;
            $this->error = $curlError;
        } catch (\Exception $e) {
            $this->response = '';
            $this->statusCode = 0;
            $this->error = $e->getMessage();

            // Re-throw critical exceptions
            if (
                $e instanceof \InvalidArgumentException ||
                ($e instanceof \RuntimeException && str_contains($e->getMessage(), 'Rate limit'))
            ) {
                throw $e;
            }
        }

        return $this;
    }

    /**
     * Checks whether new request can be made based on rate limit.
     *
     * @return bool
     */
    public function canMakeRequest(): bool
    {
        if ($this->rateLimit === null || $this->ratePeriod === null) {
            return true;
        }

        $currentTime = time();
        $elapsedTime = $currentTime - $this->periodStart;

        // Reset window period if needed
        if ($elapsedTime >= $this->ratePeriod) {
            return true; // Window reset, allowed to perform request
        }

        return $this->requestCount < $this->rateLimit;
    }

    /**
     * Returns how many requests can still be executed in current period.
     *
     * @return int|string
     */
    public function getRemainingRequests()
    {
        if ($this->rateLimit === null) {
            return 'unlimited';
        }

        $currentTime = time();
        $elapsedTime = $currentTime - $this->periodStart;

        // Reset window period if needed
        if ($elapsedTime >= $this->ratePeriod) {
            return $this->rateLimit; // Window reset
        }

        return max(0, $this->rateLimit - $this->requestCount);
    }

    /**
     * Returns duration in seconds until next rate limit reset.
     *
     * @return int
     */
    public function getTimeUntilReset(): int
    {
        if ($this->ratePeriod === null) {
            return 0;
        }

        $currentTime = time();
        $elapsedTime = $currentTime - $this->periodStart;

        return max(0, $this->ratePeriod - $elapsedTime);
    }

    /**
     * Returns status code of last request.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Returns response payload as string.
     *
     * @return string
     */
    public function getResponse(): string
    {
        return $this->response;
    }

    /**
     * Returns response as array (decodes JSON).
     *
     * @return array<mixed>
     */
    public function getArray(): array
    {
        $decoded = json_decode($this->response, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Returns response as object (decodes JSON).
     *
     * @return object|null
     */
    public function getObject(): ?object
    {
        $decoded = json_decode($this->response);
        return is_object($decoded) ? $decoded : null;
    }

    /**
     * Checks whether request succeeded (2xx status code).
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Returns error message from last request.
     *
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * Saves response payload to a file.
     *
     * @param string $filePath Target file path
     * @return bool True on success, false otherwise
     */
    public function saveToFile(string $filePath): bool
    {
        if (empty($this->response)) {
            return false;
        }

        $directory = dirname($filePath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            return false;
        }

        return file_put_contents($filePath, $this->response) !== false;
    }

    /**
     * Creates and configures cURL handle.
     *
     * @param string $url Request URL
     * @param string $method HTTP method
     * @param mixed $data Payload data to send
     * @return \CurlHandle cURL handle instance
     * @throws \RuntimeException If handle cannot be created
     */
    private function createCurlHandle(string $url, string $method, $data = null): \CurlHandle
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new \RuntimeException('Não foi possível inicializar o cURL');
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_FAILONERROR => false,
            CURLOPT_USERAGENT => 'LumHTTP/2.0'
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->prepareData($data));
        }

        return $curl;
    }

    /**
     * Prepares payload data for transmission.
     *
     * @param mixed $data Payload data to prepare
     * @return string|array<mixed>
     */
    private function prepareData($data)
    {
        if (is_array($data) || is_object($data)) {
            // Check whether array contains files
            if (is_array($data) && $this->hasFileUploads($data)) {
                return $data; // Keep as array for multipart/form-data
            }

            // If Content-Type is JSON, encode as JSON
            if ($this->isJsonContentType()) {
                return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            // Otherwise, encode as form data
            return http_build_query($data);
        }

        return (string) $data;
    }

    /**
     * Checks whether data contains file uploads.
     *
     * @param array<mixed> $data
     * @return bool
     */
    private function hasFileUploads($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        return $this->recursiveFileCheck($data);
    }

    /**
     * Recursively inspects arrays/objects for file uploads.
     *
     * @param mixed $data
     * @return bool
     */
    private function recursiveFileCheck($data): bool
    {
        if (is_array($data) || is_object($data)) {
            foreach ($data as $value) {
                if ($this->isFileUpload($value) || $this->recursiveFileCheck($value)) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Checks whether Content-Type is JSON.
     *
     * @return bool
     */
    private function isJsonContentType(): bool
    {
        foreach ($this->headers as $header) {
            if (stripos($header, 'Content-Type: application/json') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks whether specific value represents a file upload.
     *
     * @param mixed $value
     * @return bool
     */
    private function isFileUpload($value): bool
    {
        // Native cURL classes
        if ($value instanceof \CURLFile || $value instanceof \CURLStringFile) {
            return true;
        }

        // String starting with @ (legacy cURL upload format)
        if (is_string($value) && str_starts_with($value, '@')) {
            return true;
        }

        // Array with specific upload keys
        if (is_array($value)) {
            $uploadKeys = ['tmp_name', 'name', 'type', 'size', 'error'];
            $hasUploadKeys = array_intersect_key($value, array_flip($uploadKeys));

            if ($this->l_count($hasUploadKeys) >= 3) { // At least 3 upload keys
                return true;
            }
        }

        // Object with upload properties (e.g. UploadedFile from Symfony/Laravel)
        if (is_object($value)) {
            $methods = get_class_methods($value);
            $uploadMethods = ['getClientOriginalName', 'getMimeType', 'getSize', 'getPathname', 'isValid'];

            if (array_intersect($methods, $uploadMethods)) {
                return true;
            }

            // Check common upload properties
            $properties = get_object_vars($value);
            if (isset($properties['tmp_name']) || isset($properties['name']) || isset($properties['type'])) {
                return true;
            }
        }

        // File resource
        if (is_resource($value) && get_resource_type($value) === 'stream') {
            $meta = stream_get_meta_data($value);
            return isset($meta['uri']) && is_file($meta['uri']);
        }

        return false;
    }

    /**
     * Applies rate limiting if configured.
     *
     * @return void
     */
    private function applyRateLimit(): bool
    {
        if ($this->rateLimit === null || $this->ratePeriod === null) {
            return true;
        }

        $currentTime = time();
        $elapsedTime = $currentTime - $this->periodStart;

        // Reset window period if needed
        if ($elapsedTime >= $this->ratePeriod) {
            $this->requestCount = 0;
            $this->periodStart = $currentTime;
            $elapsedTime = 0;
        }

        // Check if limit was reached
        if ($this->requestCount >= $this->rateLimit) {
            $waitTime = $this->ratePeriod - $elapsedTime;

            switch ($this->rateLimitStrategy) {
                case 'throw':
                    throw new \RuntimeException(
                        sprintf(
                            'Rate limit reached: %d/%d requests. Try again in %d seconds.',
                            $this->requestCount,
                            $this->rateLimit,
                            $waitTime
                        )
                    );

                case 'skip':
                    $this->error = sprintf(
                        'Request skipped due to rate limit: %d/%d. Reset in %d seconds.',
                        $this->requestCount,
                        $this->rateLimit,
                        $waitTime
                    );
                    return false;

                case 'wait':
                default:
                    if ($waitTime > 0) {
                        // For asynchronous applications, avoids long blocking waits
                        if ($waitTime > 5) {
                            // If wait time is excessively long, throw exception
                            throw new \RuntimeException(
                                sprintf(
                                    'Rate limit reached. Wait time too long: %d seconds. Use "skip" or "throw" strategy.',
                                    $waitTime
                                )
                            );
                        }
                        sleep($waitTime);
                        $this->requestCount = 0;
                        $this->periodStart = time();
                    }
                    break;
            }
        }

        $this->requestCount++;
        return true;
    }

    /**
     * Validates URL.
     *
     * @param string $url
     * @throws \InvalidArgumentException
     */
    private function validateUrl(string $url): void
    {
        // Verify not empty
        if (empty(trim($url))) {
            throw new \InvalidArgumentException('URL cannot be empty');
        }

        // Basic format validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL format: ' . $url);
        }

        // Parse URL for specific validations
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new \InvalidArgumentException('Could not parse URL: ' . $url);
        }

        // Check if scheme is defined
        if (!isset($parsed['scheme'])) {
            throw new \InvalidArgumentException('URL must contain a protocol (http/https): ' . $url);
        }

        // Only allow HTTP and HTTPS
        $allowedSchemes = ['http', 'https'];
        if (!in_array(strtolower($parsed['scheme']), $allowedSchemes)) {
            throw new \InvalidArgumentException(
                'Unsupported protocol: ' . $parsed['scheme'] . '. Use http or https.'
            );
        }

        // Check if host is defined
        if (!isset($parsed['host']) || empty($parsed['host'])) {
            throw new \InvalidArgumentException('URL must contain a valid host: ' . $url);
        }

        // Host validation
        if (!$this->isValidHost($parsed['host'])) {
            throw new \InvalidArgumentException('Invalid host: ' . $parsed['host']);
        }

        // Verify port if specified
        if (isset($parsed['port'])) {
            if ($parsed['port'] < 1 || $parsed['port'] > 65535) {
                throw new \InvalidArgumentException('Invalid port: ' . $parsed['port']);
            }
        }
    }

    /**
     * Validates whether host is valid.
     *
     * @param string $host
     * @return bool
     */
    private function isValidHost(string $host): bool
    {
        // Strip port if present
        $host = explode(':', $host)[0];

        // Check if valid IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Check if valid hostname
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return true;
        }

        // Additional manual validation for edge cases
        if (preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $host)) {
            return true;
        }

        return false;
    }

    /**
     * Validates HTTP method rigorously.
     *
     * @param string $method
     * @throws \InvalidArgumentException
     */
    private function validateMethod(string $method): void
    {
        if (empty(trim($method))) {
            throw new \InvalidArgumentException('HTTP method cannot be empty');
        }

        $method = strtoupper(trim($method));
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT'];

        if (!in_array($method, $validMethods)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid HTTP method: %s. Supported methods: %s',
                    $method,
                    implode(', ', $validMethods)
                )
            );
        }

        // Method-specific validations
        switch ($method) {
            case 'TRACE':
            case 'CONNECT':
                // Methods generally not used in REST APIs
                trigger_error(
                    "Method $method is rarely used. Please verify if it's really necessary.",
                    E_USER_NOTICE
                );
                break;
        }
    }


    /**
     * Validates input payload data robustly.
     *
     * @param mixed $data
     * @param string $method
     * @throws \InvalidArgumentException
     */
    private function validateRequestData($data, string $method): void
    {
        // Methods that normally should not carry a body
        $methodsWithoutBody = ['GET', 'HEAD', 'DELETE', 'OPTIONS'];

        if (in_array(strtoupper($method), $methodsWithoutBody) && $data !== null) {
            trigger_error(
                "Method $method normally should not contain data in request body.",
                E_USER_WARNING
            );
        }

        // Check payload size (to prevent memory issues)
        if (is_string($data) && strlen($data) > 10 * 1024 * 1024) { // 10MB
            trigger_error(
                'Data too large (>10MB). Consider using streaming or splitting the request.',
                E_USER_WARNING
            );
        }

        // JSON data validation
        if (is_string($data) && $this->isJsonContentType()) {
            json_decode($data);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException(
                    'Invalid JSON data: ' . json_last_error_msg()
                );
            }
        }
    }

    /**
     * Validates configuration before executing request.
     *
     * @throws \InvalidArgumentException
     */
    private function validateConfiguration(): void
    {
        // Validate timeout
        if ($this->timeout <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than zero');
        }

        if ($this->timeout > 300) { // 5 minutos
            trigger_error(
                'Timeout too high (' . $this->timeout . 's). Consider using a lower value.',
                E_USER_WARNING
            );
        }

        // Validate rate limiting
        if ($this->rateLimit !== null && $this->ratePeriod !== null) {
            if ($this->rateLimit <= 0 || $this->ratePeriod <= 0) {
                throw new \InvalidArgumentException('Rate limit and period must be greater than zero');
            }

            if ($this->rateLimit > 1000) {
                trigger_error(
                    'Rate limit too high (' . $this->rateLimit . '). Please verify if it\'s correct.',
                    E_USER_WARNING
                );
            }
        }
    }

    /**
     * Returns Content-Type header based on type.
     *
     * @param string $type
     * @return string
     */
    private function getContentTypeHeader(string $type): string
    {
        $types = [
            'json' => 'Content-Type: application/json',
            'xml' => 'Content-Type: application/xml',
            'form' => 'Content-Type: application/x-www-form-urlencoded',
            'text' => 'Content-Type: text/plain',
            'multipart' => 'Content-Type: multipart/form-data'
        ];

        return $types[$type] ?? $types['json'];
    }

    /**
     * Returns Accept header based on type.
     *
     * @param string $type
     * @return string
     */
    private function getAcceptHeader(string $type): string
    {
        $types = [
            'json' => 'Accept: application/json',
            'xml' => 'Accept: application/xml',
            'form' => 'Accept: application/x-www-form-urlencoded',
            'text' => 'Accept: text/plain',
            'html' => 'Accept: text/html'
        ];

        return $types[$type] ?? $types['json'];
    }


    /**
     * Displays detailed request and rate limiting statistics.
     * Useful for debugging and development monitoring.
     *
     * @param bool $formatted Whether to return formatted string or raw array
     * @return array<string, mixed>|string
     */
    public function getRequestStats(bool $formatted = true)
    {
        $currentTime = time();
        $elapsedTime = $currentTime - $this->periodStart;
        $remainingTime = $this->ratePeriod ? max(0, $this->ratePeriod - $elapsedTime) : 0;
        $remainingRequests = $this->rateLimit ? max(0, $this->rateLimit - $this->requestCount) : 'Unlimited';

        $stats = [
            'request_info' => [
                'last_url' => $this->lastUrl ?: 'No requests made yet',
                'last_status_code' => $this->statusCode,
                'last_error' => $this->error ?: 'No errors',
                'ssl_verification' => $this->verifySSL ? 'Enabled' : 'Disabled',
                'timeout' => $this->timeout . ' seconds',
            ],
            'rate_limiting' => [
                'status' => $this->rateLimit ? 'Enabled' : 'Disabled',
                'limit_per_period' => $this->rateLimit ?: 'Not configured',
                'period_duration' => $this->ratePeriod ? $this->ratePeriod . ' seconds' : 'Not configured',
                'current_period_requests' => $this->requestCount,
                'remaining_requests' => $remainingRequests,
                'period_elapsed_time' => $elapsedTime . ' seconds',
                'time_until_reset' => $remainingTime . ' seconds',
                'period_start' => date('Y-m-d H:i:s', $this->periodStart),
                'next_reset' => $this->ratePeriod ? date('Y-m-d H:i:s', $this->periodStart + $this->ratePeriod) : 'N/A'
            ],
            'headers_count' => $this->l_count($this->headers),
            'timestamp' => date('Y-m-d H:i:s', $currentTime)
        ];

        if (!$formatted) {
            return $stats;
        }

        return $this->formatStatsOutput($stats);
    }

    /**
     * Formats statistics output for human-readable display.
     *
     * @param array<string, mixed> $stats
     * @return string
     */
    private function formatStatsOutput(array $stats): string
    {
        $output = "\n" . str_repeat("=", 60) . "\n";
        $output .= "           LUMYNUS HTTP - REQUEST STATISTICS\n";
        $output .= str_repeat("=", 60) . "\n";

        // Last request information
        $output .= "\n📊 LAST REQUEST:\n";
        $output .= str_repeat("-", 40) . "\n";
        $output .= sprintf("• URL: %s\n", $stats['request_info']['last_url']);
        $output .= sprintf("• Status Code: %s\n", $stats['request_info']['last_status_code'] ?: 'N/A');
        $output .= sprintf("• Error: %s\n", $stats['request_info']['last_error']);
        $output .= sprintf("• SSL: %s\n", $stats['request_info']['ssl_verification']);
        $output .= sprintf("• Timeout: %s\n", $stats['request_info']['timeout']);

        // Rate Limiting
        $output .= "\n🚦 RATE LIMITING:\n";
        $output .= str_repeat("-", 40) . "\n";
        $output .= sprintf("• Status: %s\n", $stats['rate_limiting']['status']);

        if ($this->rateLimit) {
            $output .= sprintf(
                "• Limit: %s requests per %s\n",
                $stats['rate_limiting']['limit_per_period'],
                $stats['rate_limiting']['period_duration']
            );
            $output .= sprintf("• Current period requests: %s\n", $stats['rate_limiting']['current_period_requests']);
            $output .= sprintf("• Remaining requests: %s\n", $stats['rate_limiting']['remaining_requests']);
            $output .= sprintf("• Elapsed time in period: %s\n", $stats['rate_limiting']['period_elapsed_time']);
            $output .= sprintf("• Time until reset: %s\n", $stats['rate_limiting']['time_until_reset']);
            $output .= sprintf("• Period start: %s\n", $stats['rate_limiting']['period_start']);
            $output .= sprintf("• Next reset: %s\n", $stats['rate_limiting']['next_reset']);

            // Visual progress bar
            $progress = $this->rateLimit > 0 ? ($this->requestCount / $this->rateLimit) * 100 : 0;
            $progressBar = $this->createProgressBar($progress);
            $output .= sprintf("• Limit progress: %s %.1f%%\n", $progressBar, $progress);
        }

        // General information
        $output .= "\n⚙️  CONFIGURATION:\n";
        $output .= str_repeat("-", 40) . "\n";
        $output .= sprintf("• Configured headers: %s\n", $stats['headers_count']);
        $output .= sprintf("• Timestamp: %s\n", $stats['timestamp']);

        $output .= "\n" . str_repeat("=", 60) . "\n";

        return $output;
    }

    /**
     * Creates visual progress bar.
     *
     * @param float $percentage
     * @return string
     */
    private function createProgressBar(float $percentage): string
    {
        $barLength = 20;
        $filledLength = (int) round(($percentage / 100) * $barLength);
        $emptyLength = $barLength - $filledLength;

        $bar = '[' . str_repeat('█', $filledLength) . str_repeat('░', $emptyLength) . ']';

        // Add color based on percentage (for supported terminals)
        if ($percentage >= 90) {
            $bar = "\033[31m" . $bar . "\033[0m"; // Vermelho
        } elseif ($percentage >= 70) {
            $bar = "\033[33m" . $bar . "\033[0m"; // Amarelo
        } else {
            $bar = "\033[32m" . $bar . "\033[0m"; // Verde
        }

        return $bar;
    }

    /**
     * Displays statistics directly to output (useful for debugging).
     *
     * @return void
     */
    public function showStats(): void
    {
        echo $this->getRequestStats(true);
    }

    /**
     * Saves statistics to a log file.
     *
     * @param string $filePath File path
     * @param bool $append Whether to append to existing file
     * @return bool
     */
    public function logStats(string $filePath, bool $append = true): bool
    {
        $stats = $this->getRequestStats(true);
        $logEntry = "\n" . date('Y-m-d H:i:s') . " - LUMYNUS HTTP STATS\n" . $stats . "\n";

        $flags = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;

        return file_put_contents($filePath, $logEntry, $flags) !== false;
    }

    /**
     * Returns a concise one-line summary of statistics.
     *
     * @return string
     */
    public function getQuickStats(): string
    {
        $rateLimitInfo = $this->rateLimit
            ? sprintf("Rate Limit: %d/%d", $this->requestCount, $this->rateLimit)
            : "Rate Limit: OFF";

        $lastStatus = $this->statusCode ? "Status: {$this->statusCode}" : "Status: N/A";

        return sprintf(
            "[LumaHTTP] %s | %s | Headers: %d | SSL: %s",
            $rateLimitInfo,
            $lastStatus,
            $this->l_count($this->headers),
            $this->verifySSL ? 'ON' : 'OFF'
        );
    }

    /**
     * Comprehensive debug test.
     *
     * Runs a test suite to verify LumaHTTP class is operating properly.
     * Includes prerequisite checks, instantiation, GET and POST requests, and output formatting.
     */
    public function debugTest()
    {
        echo "🔧 LUMAHTTP COMPLETE DEBUG TEST\n <br><br>";
        echo str_repeat("=", 50) . "\n <br><br>";

        // Check required extensions
        echo "📋 Checking prerequisites:\n <br>";
        echo "- cURL: " . (extension_loaded('curl') ? '✅ OK' : '❌ MISSING') . "\n <br>";
        echo "- JSON: " . (extension_loaded('json') ? '✅ OK' : '❌ MISSING') . "\n <br>";

        if (!extension_loaded('curl')) {
            echo "❌ cURL is not available. Please install php-curl extension.\n <br>";
            return;
        }

        try {
            echo "\n🏗️ Instantiating LumaHTTP...\n <br>";
            $http = new \Lumynus\Framework\LumaHTTP();
            echo "✅ Instance created successfully!\n <br>";

            echo "\n📡 Testing GET request...\n<br>";
            $start = microtime(true);
            $response = $http->get('https://jsonplaceholder.typicode.com/posts/1');
            $duration = round((microtime(true) - $start) * 1000, 2);

            echo "⏱️ Response time: {$duration}ms\n <br>";

            echo "<br>\n📊 Results:\n<br>";
            echo "- Status Code: " . $response->getStatusCode() . "\n<br>";
            echo "- Is Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n<br>";
            echo "- Has Error: " . (empty($response->getError()) ? 'No' : 'Yes') . "\n<br><br>";

            if (!empty($response->getError())) {
                echo "- Error: " . $response->getError() . "\n<br><br>";
            }

            $responseData = $response->getResponse();
            echo "- Response Size: " . strlen($responseData) . " bytes\n <br>";

            if ($response->isSuccess()) {
                $array = $response->getArray();
                echo "- Valid JSON: " . (is_array($array) && !empty($array) ? 'Yes' : 'No') . "\n <br><br>";

                if (!empty($array)) {
                    echo "\n📄 Received data:\n<br>";
                    foreach (['id', 'title', 'userId'] as $field) {
                        if (isset($array[$field])) {
                            $value = is_string($array[$field]) ?
                                substr($array[$field], 0, 50) . (strlen($array[$field]) > 50 ? '...' : '') :
                                $array[$field];
                            echo "- $field: " . $value . "\n<br>";
                        }
                    }
                }
            }

            echo "\n" . str_repeat("=", 50) . "\n <br>";
            echo "<br>🎉 Debug test completed successfully!\n<br><br>";
        } catch (\Throwable $e) {
            echo "<br>💥 CRITICAL ERROR:\n<br><br>";
            echo "- Type: " . get_class($e) . "\n";
            echo "- Message: " . $e->getMessage() . "\n";
            echo "- File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo "- Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * Debug information.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $sanitizedHeaders = array_map(function ($header) {
            if (stripos($header, 'Authorization:') !== false || stripos($header, 'X-API-Key:') !== false) {
                return preg_replace('/(:\s*)(.+)/', '$1[HIDDEN]', $header);
            }
            return $header;
        }, $this->headers);

        return [
            'lastUrl' => $this->lastUrl,
            'statusCode' => $this->statusCode,
            'verifySSL' => $this->verifySSL,
            'timeout' => $this->timeout,
            'headers' => $sanitizedHeaders,
            'error' => $this->error,
            'rateLimit' => $this->rateLimit,
            'ratePeriod' => $this->ratePeriod,
            'rateLimitStrategy' => $this->rateLimitStrategy ?? 'wait',
            'requestCount' => $this->requestCount,
            'canMakeRequest' => $this->canMakeRequest(),
            'remainingRequests' => $this->getRemainingRequests(),
            'timeUntilReset' => $this->getTimeUntilReset()
        ];
    }
}
