<?php

namespace App\Http\Controllers\Api;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (no auth) restaurant page by slug. For the generic [slug].domain.com page.
 */
class PublicRestaurantController extends Controller
{
    public function __construct(
        private readonly RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * Get public restaurant data by slug. Optional ?locale= for description and menu item language.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $restaurant = $this->restaurantRepository->findBySlug($slug);
        if ($restaurant === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $locale = $request->input('locale') ?? $restaurant->default_locale ?? 'en';
        $locale = strtolower($locale);
        $installedLocales = $restaurant->languages()->pluck('locale')->all();
        if (! in_array($locale, $installedLocales, true)) {
            $locale = $restaurant->default_locale ?? 'en';
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $translation = $restaurant->translations()->where('locale', $locale)->first();
        $description = $translation?->description ?? null;

        $menuItems = $restaurant->menuItems()->with('translations')->orderBy('sort_order')->orderBy('id')->get();
        $menuPayload = $menuItems->map(function ($item) use ($locale) {
            $t = $item->translations->firstWhere('locale', $locale) ?? $item->translations->first();
            return [
                'uuid' => $item->uuid,
                'name' => $t?->name ?? '',
                'description' => $t?->description ?? null,
                'sort_order' => $item->sort_order,
            ];
        })->all();

        return response()->json([
            'data' => [
                'name' => $restaurant->name,
                'tagline' => $restaurant->tagline,
                'slug' => $restaurant->slug,
                'logo_url' => $restaurant->logo_path
                    ? $baseUrl . '/api/restaurants/' . $restaurant->uuid . '/logo'
                    : null,
                'banner_url' => $restaurant->banner_path
                    ? $baseUrl . '/api/restaurants/' . $restaurant->uuid . '/banner'
                    : null,
                'default_locale' => $restaurant->default_locale ?? 'en',
                'languages' => $installedLocales,
                'locale' => $locale,
                'description' => $description,
                'menu_items' => $menuPayload,
            ],
        ]);
    }
}
