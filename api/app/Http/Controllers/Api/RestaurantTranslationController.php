<?php

namespace App\Http\Controllers\Api;

use App\Application\Restaurant\GetRestaurant;
use App\Application\Restaurant\GetRestaurantTranslations;
use App\Application\Restaurant\UpsertRestaurantTranslation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RestaurantTranslationController extends Controller
{
    public function __construct(
        private readonly GetRestaurant $getRestaurant,
        private readonly GetRestaurantTranslations $getTranslations,
        private readonly UpsertRestaurantTranslation $upsertTranslation
    ) {}

    /**
     * List all translations (locale -> { description }).
     */
    public function index(Request $request, string $restaurant): JsonResponse
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $translations = $this->getTranslations->handle($request->user(), $restaurant);

        return response()->json(['data' => $translations]);
    }

    /**
     * Get translation for a locale. Returns { description } or { description: null } if not yet set.
     */
    public function show(Request $request, string $restaurant, string $locale): JsonResponse
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $locale = strtolower($locale);
        if (! $restaurantModel->languages()->where('locale', $locale)->exists()) {
            return response()->json(['message' => __('This language is not installed for the restaurant.')], 404);
        }

        $translations = $this->getTranslations->handle($request->user(), $restaurant);

        return response()->json([
            'data' => $translations[$locale] ?? ['description' => null],
        ]);
    }

    /**
     * Create or update restaurant description for a locale.
     */
    public function update(Request $request, string $restaurant, string $locale): JsonResponse
    {
        $request->validate([
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $restaurantModel = $this->upsertTranslation->handle(
                $request->user(),
                $restaurant,
                $locale,
                $request->input('description')
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $translations = $this->getTranslations->handle($request->user(), $restaurant);
        $locale = strtolower($locale);

        return response()->json([
            'message' => __('Translation saved.'),
            'data' => $translations[$locale] ?? ['description' => $request->input('description')],
        ]);
    }
}
