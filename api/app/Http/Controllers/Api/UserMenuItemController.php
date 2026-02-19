<?php

namespace App\Http\Controllers\Api;

use App\Application\MenuItem\CreateStandaloneMenuItem;
use App\Application\MenuItem\DeleteUserMenuItem;
use App\Application\MenuItem\GetUserMenuItem;
use App\Application\MenuItem\ListUserMenuItems;
use App\Application\MenuItem\UpdateUserMenuItem;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserMenuItemController extends Controller
{
    public function __construct(
        private readonly ListUserMenuItems $listUserMenuItems,
        private readonly GetUserMenuItem $getUserMenuItem,
        private readonly CreateStandaloneMenuItem $createStandaloneMenuItem,
        private readonly UpdateUserMenuItem $updateUserMenuItem,
        private readonly DeleteUserMenuItem $deleteUserMenuItem
    ) {}

    /**
     * List all menu items the user can access (standalone + from their restaurants).
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->listUserMenuItems->handle($request->user());

        return response()->json([
            'data' => $items->map(fn (MenuItem $item) => $this->menuItemPayload($item)),
        ]);
    }

    /**
     * Show a single menu item (standalone or from a restaurant the user owns).
     */
    public function show(Request $request, string $item): JsonResponse
    {
        $menuItem = $this->getUserMenuItem->handle($request->user(), $item);
        if ($menuItem === null) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        return response()->json(['data' => $this->menuItemPayload($menuItem)]);
    }

    /**
     * Create a standalone menu item (not tied to any restaurant).
     */
    public function store(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
        ]);

        $translations = $validated['translations'];
        $hasName = collect($translations)->contains(fn ($t) => ! empty(trim($t['name'] ?? '')));
        if (! $hasName) {
            return response()->json([
                'message' => __('At least one translation must have a name.'),
                'errors' => ['translations' => [__('Name is required for at least one language.')]],
            ], 422);
        }

        $menuItem = $this->createStandaloneMenuItem->handle($request->user(), $validated);

        return response()->json([
            'message' => __('Menu item created.'),
            'data' => $this->menuItemPayload($menuItem),
        ], 201);
    }

    /**
     * Update a menu item (standalone or from a restaurant the user owns).
     */
    public function update(Request $request, string $item): JsonResponse|Response
    {
        $validated = $request->validate([
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
        ]);

        $menuItem = $this->updateUserMenuItem->handle($request->user(), $item, $validated);
        if ($menuItem === null) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        return response()->json([
            'message' => __('Menu item updated.'),
            'data' => $this->menuItemPayload($menuItem),
        ]);
    }

    /**
     * Delete a menu item (standalone or from a restaurant the user owns).
     */
    public function destroy(Request $request, string $item): JsonResponse|Response
    {
        $ok = $this->deleteUserMenuItem->handle($request->user(), $item);
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
        if ($item->source_menu_item_uuid !== null && $item->relationLoaded('sourceMenuItem')) {
            $translations = $item->getEffectiveTranslations();
        }

        $payload = [
            'uuid' => $item->uuid,
            'category_uuid' => $item->category?->uuid,
            'sort_order' => $item->sort_order,
            'price' => $item->getEffectivePrice(),
            'translations' => $translations,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];

        if ($item->relationLoaded('restaurant')) {
            $payload['restaurant_uuid'] = $item->restaurant?->uuid;
        }

        return $payload;
    }
}
