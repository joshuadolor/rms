<?php

namespace App\Services\Translation;

use App\Contracts\TranslationServiceInterface;
use App\Exceptions\TranslationException;
use Illuminate\Support\Facades\Http;

/**
 * LibreTranslate API client. Configure LIBRE_TRANSLATE_URL (and optionally LIBRE_TRANSLATE_API_KEY).
 */
final class LibreTranslateService implements TranslationServiceInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null
    ) {}

    public function translate(string $text, string $fromLocale, string $toLocale): string
    {
        if ($text === '') {
            return '';
        }

        $payload = [
            'q' => $text,
            'source' => $fromLocale,
            'target' => $toLocale,
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $payload['api_key'] = $this->apiKey;
        }

        $response = Http::timeout(15)
            ->post(rtrim($this->baseUrl, '/') . '/translate', $payload);

        if (! $response->successful()) {
            throw new TranslationException(
                'LibreTranslate error: ' . ($response->json('error') ?? $response->body() ?: $response->reason()),
                null
            );
        }

        $translated = $response->json('translatedText');

        if (! is_string($translated)) {
            throw new TranslationException('LibreTranslate returned invalid response.');
        }

        return $translated;
    }

    public function isAvailable(): bool
    {
        return $this->baseUrl !== '';
    }
}
