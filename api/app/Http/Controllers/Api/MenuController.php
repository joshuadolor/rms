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

        return response()->json([
            'data' => $menus->map(fn (Menu $m) => $this->menuPayload($m)),
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

        return response()->json(['data' => $this->menuPayload($menuModel)]);
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
        ]);

        try {
            $menu = $this->createMenu->handle($request->user(), $restaurant, $validated);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($menu === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        return response()->json([
            'message' => __('Menu created.'),
            'data' => $this->menuPayload($menu),
        ], 201);
    }

    /**
     * Update a menu.
     */
    public function update(Request $request, string $restaurant, string $menu): JsonResponse|Response
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $menuModel = $this->updateMenu->handle($request->user(), $restaurant, $menu, $validated);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($menuModel === null) {
            return response()->json(['message' => __('Menu not found.')], 404);
        }

        return response()->json([
            'message' => __('Menu updated.'),
            'data' => $this->menuPayload($menuModel),
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
    private function menuPayload(Menu $menu): array
    {
        return [
            'uuid' => $menu->uuid,
            'name' => $menu->name,
            'is_active' => $menu->is_active,
            'sort_order' => $menu->sort_order,
            'created_at' => $menu->created_at?->toIso8601String(),
            'updated_at' => $menu->updated_at?->toIso8601String(),
        ];
    }
}
