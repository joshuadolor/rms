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

        $codes = array_column($supported, 'code');
        $from = $request->input('from_locale');
        $to = $request->input('to_locale');

        if (! in_array($from, $codes, true)) {
            return response()->json([
                'message' => __('The source language is not supported by the translation service.'),
                'errors' => ['from_locale' => [__('Language not supported.')]],
            ], 422);
        }

        if (! in_array($to, $codes, true)) {
            return response()->json([
                'message' => __('The target language is not supported by the translation service.'),
                'errors' => ['to_locale' => [__('Language not supported.')]],
            ], 422);
        }

        try {
            $translated = $this->translationService->translate(
                $request->input('text'),
                $from,
                $to
            );
        } catch (TranslationException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'translated_text' => $translated,
        ]);
    }
}
