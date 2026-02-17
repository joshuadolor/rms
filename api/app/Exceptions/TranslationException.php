<?php

namespace App\Exceptions;

/**
 * Thrown when translation service fails (e.g. LibreTranslate unreachable or error).
 */
class TranslationException extends ApiException
{
    public function __construct(string $message = 'Translation failed.', ?\Throwable $previous = null)
    {
        parent::__construct(500, $message, $previous);
    }
}
