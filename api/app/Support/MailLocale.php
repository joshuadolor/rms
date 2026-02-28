<?php

namespace App\Support;

use Illuminate\Http\Request;

class MailLocale
{
    /**
     * Supported locales for transactional emails (must match legal/locale config).
     *
     * @var list<string>
     */
    private const SUPPORTED = ['en', 'es', 'ar'];

    /**
     * Resolve locale for sending emails from the request.
     * Prefers body "locale", then Accept-Language header, then default "en".
     */
    public static function resolve(Request $request): string
    {
        $locale = $request->input('locale');
        if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
            return $locale;
        }

        $header = $request->header('Accept-Language');
        if (is_string($header)) {
            $parsed = self::parseAcceptLanguage($header);
            if ($parsed !== null && in_array($parsed, self::SUPPORTED, true)) {
                return $parsed;
            }
        }

        return 'en';
    }

    /**
     * Parse Accept-Language and return the first language code (e.g. "es-ES" -> "es").
     */
    private static function parseAcceptLanguage(string $value): ?string
    {
        $parts = array_map('trim', explode(',', $value));
        foreach ($parts as $part) {
            $lang = explode(';', $part)[0];
            $lang = trim($lang);
            if ($lang === '') {
                continue;
            }
            $code = explode('-', $lang)[0];
            return strtolower($code);
        }

        return null;
    }

    /**
     * Validation rule for optional locale in request.
     *
     * @return array<int, mixed>
     */
    public static function validationRule(): array
    {
        return ['sometimes', 'string', 'max:5', 'in:'.implode(',', self::SUPPORTED)];
    }
}
