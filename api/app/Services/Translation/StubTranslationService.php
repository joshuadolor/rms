<?php

namespace App\Services\Translation;

use App\Contracts\TranslationServiceInterface;

/**
 * No-op implementation: returns the original text. Use when LibreTranslate is not configured.
 */
final class StubTranslationService implements TranslationServiceInterface
{
    public function translate(string $text, string $fromLocale, string $toLocale): string
    {
        return $text !== '' ? $text : '';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{code: string, name: string, targets?: array<string>}>
     */
    public function getSupportedLanguages(): array
    {
        return [];
    }
}
