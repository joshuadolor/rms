<?php

namespace App\Http\Controllers\Api;

use App\Contracts\TranslationServiceInterface;
use App\Exceptions\TranslationException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslateController extends Controller
{
    public function __construct(
        private readonly TranslationServiceInterface $translationService
    ) {}

    /**
     * List languages supported by the external translation service (e.g. LibreTranslate).
     * Proxies the service’s languages endpoint so the frontend knows what is available.
     */
    public function languages(): JsonResponse
    {
        if (! $this->translationService->isAvailable()) {
            return response()->json([
                'message' => __('Translation service is not configured. Set LIBRE_TRANSLATE_URL to use machine translation.'),
            ], 503);
        }

        try {
            $list = $this->translationService->getSupportedLanguages();
        } catch (TranslationException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['data' => $list]);
    }

    /**
     * Translate text via the external service only. No in-app translation logic; validates input,
     * checks that from/to locales are supported by the service, then calls the service and returns its response.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:50000'],
            'from_locale' => ['required', 'string', 'max:10'],
            'to_locale' => ['required', 'string', 'max:10'],
        ]);

        if (! $this->translationService->isAvailable()) {
            return response()->json([
                'message' => __('Translation service is not configured. Set LIBRE_TRANSLATE_URL to use machine translation.'),
            ], 503);
        }

        try {
            $supported = $this->translationService->getSupportedLanguages();
        } catch (TranslationException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $serviceCodes = array_column($supported, 'code');
        // Allow any locale supported for restaurants so "Translate" works for all installed languages;
        // if the external service cannot translate that language, it will throw and we return 502.
        $appLocales = config('locales.supported', []);
        $allowedCodes = array_values(array_unique(array_merge($serviceCodes, $appLocales)));

        $from = $request->input('from_locale');
        $to = $request->input('to_locale');

        if (! in_array($from, $allowedCodes, true)) {
            return response()->json([
                'message' => __('The source language is not supported by the translation service.'),
                'errors' => ['from_locale' => [__('Language not supported.')]],
            ], 422);
        }

        if (! in_array($to, $allowedCodes, true)) {
            return response()->json([
                'message' => __('The target language is not supported by the translation service.'),
                'errors' => ['to_locale' => [__('Language not supported.')]],
            ], 422);
        }

        $text = $request->input('text');

        try {
            $translated = $this->translationService->translate($text, $from, $to);
        } catch (TranslationException $e) {
            // When the service does not support the language (e.g. "ar is not supported"), return
            // the original text so the UI does not break; the user can edit or paste a translation.
            $msg = $e->getMessage();
            $isUnsupported = stripos($msg, 'not support') !== false || stripos($msg, 'not available') !== false;
            if ($isUnsupported) {
                return response()->json([
                    'translated_text' => $text,
                    'fallback' => true,
                    'message' => __('Translation is not available for this language. Original text shown.'),
                ]);
            }
            return response()->json(['message' => $msg], 502);
        }

        return response()->json([
            'translated_text' => $translated,
        ]);
    }
}
