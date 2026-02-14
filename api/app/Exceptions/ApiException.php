<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Base exception for API errors. Rendered as JSON: { "message": "..." }.
 * Handled centrally in bootstrap/app.php for requests to /api/*.
 */
class ApiException extends HttpException
{
    public function __construct(
        int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }
}
