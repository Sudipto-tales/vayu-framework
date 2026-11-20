<?php

namespace Vayu\Exceptions;

/**
 * An exception that carries an HTTP status code.
 */
class HttpException extends \RuntimeException
{
    public function __construct(
        protected int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
