<?php

namespace App\Exceptions;

/**
 * 403 Forbidden — authenticated but email address is not verified.
 */
class UnverifiedEmailException extends ApiException
{
    public const MESSAGE = 'Your email address is not verified.';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(403, self::MESSAGE, $previous);
    }
}
