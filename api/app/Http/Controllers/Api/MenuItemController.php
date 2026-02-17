<?php

namespace App\Http\Controllers\Api;

use App\Application\MenuItem\CreateMenuItem;
use App\Application\MenuItem\DeleteMenuItem;
use App\Application\MenuItem\GetMenuItem;
use App\Application\MenuItem\ListMenuItems;
use App\Application\MenuItem\UpdateMenuItem;
use App\Application\Restaurant\GetRestaurant;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MenuItemController extends Controller
{
    public function __construct(
        private readonly GetRestaurant $getRestaurant,
        private readonly ListMenuItems $listMenuItems,
        private readonly GetMenuItem $getMenuItem,
        private readonly CreateMenuItem $createMenuItem,
        private readonly UpdateMenuItem $updateMenuItem,
        private readonly DeleteMenuItem $deleteMenuItem
    ) {}

    /**
     * List menu items for the restaurant.
     */
    public function index(Request $request, string $restaurant): JsonResponse
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $items = $this->listMenuItems->handle($request->user(), $restaurant);
        if ($items === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        return response()->json([
            'data' => $items->map(fn (MenuItem $item) => $this->menuItemPayload($item)),
        ]);
    }

    /**
     * Show a single menu item.
     */
    public function show(Request $request, string $restaurant, string $item): JsonResponse
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $menuItem = $this->getMenuItem->handle($request->user(), $restaurant, $item);
        if ($menuItem === null) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        return response()->json(['data' => $this->menuItemPayload($menuItem)]);
    }

    /**
     * Create a menu item (with optional translations).
     */
    public function store(Request $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $request->validate([
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
        ]);

        $translations = $request->input('translations', []);
        $installedLocales = $restaurantModel->languages()->pluck('locale')->all();
        $invalidLocales = array_keys($translations);
        $invalidLocales = array_diff($invalidLocales, $installedLocales);
        if ($invalidLocales !== []) {
            return response()->json([
                'message' => __('One or more translation locales are not installed for this restaurant. Add them under Restaurant → Languages first.'),
                'errors' => ['translations' => [__('Uninstalled locale(s): :locales', ['locales' => implode(', ', array_values($invalidLocales))])]],
            ], 422);
        }

        try {
            $menuItem = $this->createMenuItem->handle($request->user(), $restaurant, $request->validated());
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($menuItem === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        return response()->json([
            'message' => __('Menu item created.'),
            'data' => $this->menuItemPayload($menuItem),
        ], 201);
    }

    /**
     * Update a menu item.
     */
    public function update(Request $request, string $restaurant, string $item): JsonResponse|Response
    {
        $request->validate([
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $menuItem = $this->updateMenuItem->handle($request->user(), $restaurant, $item, $request->validated());
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($menuItem === null) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        return response()->json([
            'message' => __('Menu item updated.'),
            'data' => $this->menuItemPayload($menuItem),
        ]);
    }

    /**
     * Delete a menu item.
     */
    public function destroy(Request $request, string $restaurant, string $item): JsonResponse|Response
    {
        try {
            $ok = $this->deleteMenuItem->handle($request->user(), $restaurant, $item);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if (! $ok) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function menuItemPayload(MenuItem $item): array
    {
        $translations = [];
        foreach ($item->translations as $t) {
            $translations[$t->locale] = [
                'name' => $t->name,
                'description' => $t->description,
            ];
        }

        return [
            'uuid' => $item->uuid,
            'sort_order' => $item->sort_order,
            'translations' => $translations,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }
}
