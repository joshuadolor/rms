<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteLegal;
use Illuminate\Http\JsonResponse;

class LegalContentController extends Controller
{
    /**
     * Public: get Terms of Service content (no auth).
     * Query param: locale (optional; en, es, ar). Fallback to en if missing or invalid.
     */
    public function terms(\Illuminate\Http\Request $request): JsonResponse
    {
        $legal = SiteLegal::instance();
        $locale = SiteLegal::resolveLocale($request->query('locale'));

        return response()->json([
            'data' => [
                'content' => $legal->getTermsForLocale($locale),
            ],
        ]);
    }

    /**
     * Public: get Privacy Policy content (no auth).
     * Query param: locale (optional; en, es, ar). Fallback to en if missing or invalid.
     */
    public function privacy(\Illuminate\Http\Request $request): JsonResponse
    {
        $legal = SiteLegal::instance();
        $locale = SiteLegal::resolveLocale($request->query('locale'));

        return response()->json([
            'data' => [
                'content' => $legal->getPrivacyForLocale($locale),
            ],
        ]);
    }
}
