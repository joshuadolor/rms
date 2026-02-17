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
     * Translate text using the configured service (e.g. LibreTranslate). For use when adding translations.
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
            $translated = $this->translationService->translate(
                $request->input('text'),
                $request->input('from_locale'),
                $request->input('to_locale')
            );
        } catch (TranslationException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'translated_text' => $translated,
        ]);
    }
}
