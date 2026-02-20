<?php

namespace App\Http\Controllers\Api;

use App\Application\MenuItem\CreateMenuItem;
use App\Application\MenuItem\DeleteMenuItem;
use App\Application\MenuItem\GetMenuItem;
use App\Application\MenuItem\ListMenuItems;
use App\Application\MenuItem\ReorderMenuItems;
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
        private readonly DeleteMenuItem $deleteMenuItem,
        private readonly ReorderMenuItems $reorderMenuItems
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

        $validated = $request->validate([
            'category_uuid' => ['nullable', 'string', 'uuid'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'source_menu_item_uuid' => ['nullable', 'string', 'uuid'],
            'source_variant_uuid' => ['nullable', 'string', 'uuid'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'translation_overrides' => ['nullable', 'array'],
            'translation_overrides.*.name' => ['nullable', 'string', 'max:255'],
            'translation_overrides.*.description' => ['nullable', 'string', 'max:5000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
            'tag_uuids' => ['nullable', 'array'],
            'tag_uuids.*' => ['string', 'uuid'],
        ]);

        if (empty($validated['source_menu_item_uuid'])) {
            $translations = $validated['translations'] ?? [];
            $installedLocales = $restaurantModel->languages()->pluck('locale')->all();
            $invalidLocales = array_keys($translations);
            $invalidLocales = array_diff($invalidLocales, $installedLocales);
            if ($invalidLocales !== []) {
                return response()->json([
                    'message' => __('One or more translation locales are not installed for this restaurant. Add them under Restaurant → Languages first.'),
                    'errors' => ['translations' => [__('Uninstalled locale(s): :locales', ['locales' => implode(', ', array_values($invalidLocales))])]],
                ], 422);
            }
        }

        try {
            $menuItem = $this->createMenuItem->handle($request->user(), $restaurant, $validated);
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
        $validated = $request->validate([
            'category_uuid' => ['nullable', 'string', 'uuid'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'translation_overrides' => ['nullable', 'array'],
            'translation_overrides.*.name' => ['nullable', 'string', 'max:255'],
            'translation_overrides.*.description' => ['nullable', 'string', 'max:5000'],
            'revert_to_base' => ['nullable', 'boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
            'tag_uuids' => ['nullable', 'array'],
            'tag_uuids.*' => ['string', 'uuid'],
        ]);

        try {
            $menuItem = $this->updateMenuItem->handle($request->user(), $restaurant, $item, $validated);
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
        $effectiveTranslations = $item->getEffectiveTranslations();
        $tags = $item->relationLoaded('menuItemTags')
            ? $item->menuItemTags->map(fn ($t) => $t->toTagPayload())->values()->all()
            : [];
        $payload = [
            'uuid' => $item->uuid,
            'category_uuid' => $item->category?->uuid,
            'sort_order' => $item->sort_order,
            'is_active' => (bool) $item->is_active,
            'is_available' => (bool) ($item->is_available ?? true),
            'price' => $item->getEffectivePrice(),
            'translations' => $effectiveTranslations,
            'tags' => $tags,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];

        if ($item->source_menu_item_uuid !== null) {
            $payload['source_menu_item_uuid'] = $item->source_menu_item_uuid;
            if ($item->source_variant_uuid !== null) {
                $payload['source_variant_uuid'] = $item->source_variant_uuid;
            }
            $payload['price_override'] = $item->price_override !== null ? (float) $item->price_override : null;
            $payload['translation_overrides'] = $item->translation_overrides ?? [];
            if ($item->source_variant_uuid !== null && $item->relationLoaded('sourceVariantSku') && $item->sourceVariantSku) {
                $payload['base_price'] = (float) $item->sourceVariantSku->price;
            } else {
                $payload['base_price'] = $item->sourceMenuItem ? (float) $item->sourceMenuItem->price : null;
            }
            $baseTranslations = [];
            if ($item->relationLoaded('sourceMenuItem') && $item->sourceMenuItem) {
                foreach ($item->sourceMenuItem->translations as $t) {
                    $baseTranslations[$t->locale] = ['name' => $t->name ?? '', 'description' => $t->description];
                }
            }
            $payload['base_translations'] = $baseTranslations;
            $payload['has_overrides'] = $item->hasOverrides();
        }

        return $payload;
    }

    /**
     * Reorder menu items within a category. Body: { "order": ["uuid1", "uuid2", ...] }
     */
    public function reorder(Request $request, string $restaurant, string $category): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string', 'uuid'],
        ]);

        $ok = $this->reorderMenuItems->handle($request->user(), $restaurant, $category, $request->input('order'));
        if (! $ok) {
            return response()->json(['message' => __('Category not found.')], 404);
        }

        return response()->json(['message' => __('Order updated.')]);
    }
}
