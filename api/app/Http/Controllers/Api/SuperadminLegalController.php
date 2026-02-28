<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteLegal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperadminLegalController extends Controller
{
    /**
     * Get current Terms of Service and Privacy Policy for all locales (superadmin only).
     */
    public function show(): JsonResponse
    {
        $legal = SiteLegal::instance();

        return response()->json([
            'data' => $legal->toLocalePayload(),
        ]);
    }

    /**
     * Update Terms of Service and/or Privacy Policy per locale (superadmin only).
     * Body: { "en": { "terms_of_service": "...", "privacy_policy": "..." }, "es": { ... }, "ar": { ... } }
     */
    public function update(Request $request): JsonResponse
    {
        $supported = SiteLegal::supportedLocales();
        $rules = [];
        foreach ($supported as $locale) {
            $rules["{$locale}"] = ['sometimes', 'array'];
            $rules["{$locale}.terms_of_service"] = ['sometimes', 'nullable', 'string'];
            $rules["{$locale}.privacy_policy"] = ['sometimes', 'nullable', 'string'];
        }
        $validated = $request->validate($rules);

        $legal = SiteLegal::instance();

        foreach ($supported as $locale) {
            $payload = $validated[$locale] ?? [];
            if (array_key_exists('terms_of_service', $payload)) {
                $legal->{"terms_of_service_{$locale}"} = $payload['terms_of_service'];
            }
            if (array_key_exists('privacy_policy', $payload)) {
                $legal->{"privacy_policy_{$locale}"} = $payload['privacy_policy'];
            }
        }

        $legal->save();

        return response()->json([
            'message' => 'Legal content updated.',
            'data' => $legal->toLocalePayload(),
        ]);
    }
}
