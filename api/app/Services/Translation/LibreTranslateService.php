<?php

namespace App\Services\Translation;

use App\Contracts\TranslationServiceInterface;
use App\Exceptions\TranslationException;
use Illuminate\Http\Client\ConnectionException;
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

        $source = $this->mapToServiceCode($fromLocale);
        $target = $this->mapToServiceCode($toLocale);

        $payload = [
            'q' => $text,
            'source' => $source,
            'target' => $target,
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $payload['api_key'] = $this->apiKey;
        }

        try {
            $response = Http::timeout(15)
                ->post(rtrim($this->baseUrl, '/') . '/translate', $payload);
        } catch (ConnectionException $e) {
            throw new TranslationException(
                'Translation service is temporarily unavailable. The LibreTranslate container may still be starting (it can take a minute). Please try again.',
                $e
            );
        }

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

    /**
     * @return array<int, array{code: string, name: string, targets?: array<string>}>
     *
     * @throws TranslationException
     */
    public function getSupportedLanguages(): array
    {
        $url = rtrim($this->baseUrl, '/') . '/languages';

        try {
            $request = Http::timeout(10)->get($url);
        } catch (ConnectionException $e) {
            throw new TranslationException(
                'Translation service is temporarily unavailable. The LibreTranslate container may still be starting (it can take a minute). Please try again.',
                $e
            );
        }

        if (! $request->successful()) {
            throw new TranslationException(
                'LibreTranslate languages error: ' . ($request->json('error') ?? $request->body() ?: $request->reason()),
                null
            );
        }

        $languages = $request->json();
        if (! is_array($languages)) {
            throw new TranslationException('LibreTranslate returned invalid languages response.');
        }

        $normalized = [];
        foreach ($languages as $item) {
            if (! is_array($item) || ! isset($item['code']) || ! is_string($item['code'])) {
                continue;
            }
            $entry = [
                'code' => $item['code'],
                'name' => isset($item['name']) && is_string($item['name']) ? $item['name'] : $item['code'],
            ];
            if (isset($item['targets']) && is_array($item['targets'])) {
                $entry['targets'] = $item['targets'];
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * Map app locale code to the code expected by the translation service.
     * Uses config('translation.locale_map'); falls back to the given code if not mapped.
     */
    private function mapToServiceCode(string $appCode): string
    {
        $map = config('translation.locale_map', []);

        return $map[$appCode] ?? $appCode;
    }
}
