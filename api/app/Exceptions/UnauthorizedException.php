<?php

namespace App\Exceptions;

/**
 * 401 Unauthorized — missing or invalid authentication.
 */
class UnauthorizedException extends ApiException
{
    public function __construct(string $message = 'Unauthenticated.', ?\Throwable $previous = null)
    {
        parent::__construct(401, $message, $previous);
    }
}
