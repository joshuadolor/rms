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

    /**
     * List of languages supported by the external service. Each element is an array with at least
     * "code" and "name" (e.g. ["code" => "en", "name" => "English"]). Used for validation and
     * for proxying to clients. Returns empty array when service is not available or on error.
     *
     * @return array<int, array{code: string, name: string, targets?: array<string>}>
     *
     * @throws \App\Exceptions\TranslationException If the service is available but the request fails
     */
    public function getSupportedLanguages(): array;
}
