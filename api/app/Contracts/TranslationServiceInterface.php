<?php

namespace App\Contracts;

interface TranslationServiceInterface
{
    /**
     * Translate text from one locale to another.
     *
     * @param  non-empty-string  $text
     * @param  non-empty-string  $fromLocale  e.g. "en"
     * @param  non-empty-string  $toLocale  e.g. "nl"
     * @return non-empty-string Translated text
     *
     * @throws \App\Exceptions\TranslationException If translation fails
     */
    public function translate(string $text, string $fromLocale, string $toLocale): string;

    /**
     * Whether the service is available (e.g. LibreTranslate configured and reachable).
     */
    public function isAvailable(): bool;
}
