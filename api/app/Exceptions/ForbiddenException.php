<?php

namespace App\Exceptions;

/**
 * 403 Forbidden — insufficient permission or access refused.
 */
class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'Forbidden.', ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $previous);
    }
}
