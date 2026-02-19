<?php

namespace App\Http\Controllers\Api;

use App\Application\Menu\CreateMenu;
use App\Application\Menu\DeleteMenu;
use App\Application\Menu\GetMenu;
use App\Application\Menu\ListMenus;
use App\Application\Menu\ReorderMenus;
use App\Application\Menu\UpdateMenu;
use App\Application\Restaurant\GetRestaurant;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MenuController extends Controller
{
    public function __construct(
        private readonly GetRestaurant $getRestaurant,
        private readonly ListMenus $listMenus,
        private readonly GetMenu $getMenu,
        private readonly CreateMenu $createMenu,
        private readonly UpdateMenu $updateMenu,
        private readonly DeleteMenu $deleteMenu,
        private readonly ReorderMenus $reorderMenus
    ) {}

    /**
     * List menus for the restaurant.
     */
    public function index(Request $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $menus = $this->listMenus->handle($request->user(), $restaurant);
        if ($menus === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $defaultLocale = $restaurantModel->default_locale ?? 'en';
        return response()->json([
            'data' => $menus->map(fn (Menu $m) => $this->menuPayload($m, $defaultLocale)),
        ]);
    }

    /**
     * Show a single menu.
     */
    public function show(Request $request, string $restaurant, string $menu): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $menuModel = $this->getMenu->handle($request->user(), $restaurant, $menu);
        if ($menuModel === null) {
            return response()->json(['message' => __('Menu not found.')], 404);
        }

        $defaultLocale = $restaurantModel->default_locale ?? 'en';
        return response()->json(['data' => $this->menuPayload($menuModel, $defaultLocale)]);
    }

    /**
     * Create a menu.
     */
    public function store(Request $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:65535'],
        ]);
        $translations = $validated['translations'] ?? [];
        if ($translations !== []) {
            $installedLocales = $restaurantModel->languages()->pluck('locale')->all();
            $invalidLocales = array_diff(array_keys($translations), $installedLocales);
            if ($invalidLocales !== []) {
                return response()->json([
                    'message' => __('One or more translation locales are not installed for this restaurant.'),
                    'errors' => ['translations' => [__('Uninstalled locale(s): :locales', ['locales' => implode(', ', array_values($invalidLocales))])]],
                ], 422);
            }
        }

        try {
            $menu = $this->createMenu->handle($request->user(), $restaurant, $validated);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($menu === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $defaultLocale = $restaurantModel->default_locale ?? 'en';
        return response()->json([
            'message' => __('Menu created.'),
            'data' => $this->menuPayload($menu, $defaultLocale),
        ], 201);
    }

    /**
     * Update a menu.
     */
    public function update(Request $request, string $restaurant, string $menu): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:65535'],
        ]);
        $translations = $validated['translations'] ?? [];
        if ($translations !== []) {
            $installedLocales = $restaurantModel->languages()->pluck('locale')->all();
            $invalidLocales = array_diff(array_keys($translations), $installedLocales);
            if ($invalidLocales !== []) {
                return response()->json([
                    'message' => __('One or more translation locales are not installed for this restaurant.'),
                    'errors' => ['translations' => [__('Uninstalled locale(s): :locales', ['locales' => implode(', ', array_values($invalidLocales))])]],
                ], 422);
            }
        }

        try {
            $menuModel = $this->updateMenu->handle($request->user(), $restaurant, $menu, $validated);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($menuModel === null) {
            return response()->json(['message' => __('Menu not found.')], 404);
        }

        $defaultLocale = $restaurantModel->default_locale ?? 'en';
        return response()->json([
            'message' => __('Menu updated.'),
            'data' => $this->menuPayload($menuModel, $defaultLocale),
        ]);
    }

    /**
     * Delete a menu.
     */
    public function destroy(Request $request, string $restaurant, string $menu): JsonResponse|Response
    {
        try {
            $ok = $this->deleteMenu->handle($request->user(), $restaurant, $menu);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if (! $ok) {
            return response()->json(['message' => __('Menu not found.')], 404);
        }

        return response()->noContent();
    }

    /**
     * Reorder menus. Body: { "order": ["uuid1", "uuid2", ...] }
     */
    public function reorder(Request $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string', 'uuid'],
        ]);

        $ok = $this->reorderMenus->handle($request->user(), $restaurant, $request->input('order'));
        if (! $ok) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        return response()->json(['message' => __('Order updated.')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function menuPayload(Menu $menu, ?string $defaultLocale = null): array
    {
        $translations = [];
        if ($menu->relationLoaded('translations')) {
            foreach ($menu->translations as $t) {
                $translations[$t->locale] = [
                    'name' => $t->name,
                    'description' => $t->description,
                ];
            }
        }
        $resolvedName = $menu->name;
        if ($defaultLocale !== null && isset($translations[$defaultLocale]['name'])) {
            $resolvedName = $translations[$defaultLocale]['name'];
        }

        return [
            'uuid' => $menu->uuid,
            'name' => $resolvedName,
            'is_active' => $menu->is_active,
            'sort_order' => $menu->sort_order,
            'translations' => $translations,
            'created_at' => $menu->created_at?->toIso8601String(),
            'updated_at' => $menu->updated_at?->toIso8601String(),
        ];
    }
}
