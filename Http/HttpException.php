<?php

declare(strict_types=1);

namespace Lumynus\Http;

class HttpException extends \Exception
{
    protected ?string $format;
    protected int $statusCode;

    /**
     * Creates an HTTP exception.
     *
     * @param string $message Error message.
     * @param int $statusCode HTTP status code.
     * @param string|null $format Forced response format, or null to negotiate.
     */
    public function __construct(
        string $message,
        int $statusCode = 500,
        ?string $format = null
    ) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->format = $format;
    }

    /**
     * Returns the forced response format.
     *
     * @return string|null Forced format, or null when the request should negotiate.
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * Returns the HTTP status code.
     *
     * @return int HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
