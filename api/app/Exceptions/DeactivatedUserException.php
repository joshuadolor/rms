<?php

namespace App\Exceptions;

/**
 * 403 Forbidden — login refused because the user account has been deactivated.
 */
class DeactivatedUserException extends ApiException
{
    public const MESSAGE = 'Your account has been deactivated.';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(403, self::MESSAGE, $previous);
    }
}
