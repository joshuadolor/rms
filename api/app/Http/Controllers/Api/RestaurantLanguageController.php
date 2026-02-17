<?php

namespace App\Http\Controllers\Api;

use App\Application\Restaurant\AddRestaurantLanguage;
use App\Application\Restaurant\GetRestaurant;
use App\Application\Restaurant\ListRestaurantLanguages;
use App\Application\Restaurant\RemoveRestaurantLanguage;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class RestaurantLanguageController extends Controller
{
    public function __construct(
        private readonly GetRestaurant $getRestaurant,
        private readonly ListRestaurantLanguages $listLanguages,
        private readonly AddRestaurantLanguage $addLanguage,
        private readonly RemoveRestaurantLanguage $removeLanguage
    ) {}

    /**
     * List installed languages for the restaurant.
     */
    public function index(Request $request, string $restaurant): JsonResponse
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $locales = $this->listLanguages->handle($request->user(), $restaurant);

        return response()->json(['data' => $locales]);
    }

    /**
     * Add a language (locale) to the restaurant.
     */
    public function store(Request $request, string $restaurant): JsonResponse|Response
    {
        $request->validate([
            'locale' => ['required', 'string', 'max:10', 'in:'.implode(',', config('locales.supported', ['en', 'nl', 'ru']))],
        ]);

        try {
            $restaurantModel = $this->addLanguage->handle($request->user(), $restaurant, $request->input('locale'));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $locales = $restaurantModel->languages->pluck('locale')->values()->all();

        return response()->json([
            'message' => __('Language added.'),
            'data' => $locales,
        ], 201);
    }

    /**
     * Remove a language from the restaurant.
     */
    public function destroy(Request $request, string $restaurant, string $locale): JsonResponse|Response
    {
        try {
            $ok = $this->removeLanguage->handle($request->user(), $restaurant, $locale);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if (! $ok) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        return response()->noContent();
    }
}
