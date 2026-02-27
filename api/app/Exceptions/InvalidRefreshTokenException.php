<?php

namespace App\Exceptions;

/**
 * 401 Unauthorized — refresh token is missing, invalid, revoked, or expired.
 */
class InvalidRefreshTokenException extends ApiException
{
    public const MESSAGE = 'Invalid or expired refresh token.';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(401, self::MESSAGE, $previous);
    }
}

