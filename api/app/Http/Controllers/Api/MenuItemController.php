<?php

namespace App\Http\Controllers\Api;

use App\Application\MenuItem\CreateMenuItem;
use App\Application\MenuItem\DeleteMenuItem;
use App\Application\MenuItem\DeleteMenuItemImage;
use App\Application\MenuItem\DeleteMenuItemVariantImage;
use App\Application\MenuItem\GetMenuItem;
use App\Application\MenuItem\ListMenuItems;
use App\Application\MenuItem\ReorderMenuItems;
use App\Application\MenuItem\UpdateMenuItem;
use App\Application\MenuItem\UploadMenuItemImage;
use App\Application\MenuItem\UploadMenuItemVariantImage;
use App\Application\Restaurant\GetRestaurant;
use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadMenuItemImageRequest;
use App\Models\MenuItem;
use App\Models\MenuItemVariantSku;
use App\Rules\OperatingHoursRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MenuItemController extends Controller
{
    public function __construct(
        private readonly GetRestaurant $getRestaurant,
        private readonly ListMenuItems $listMenuItems,
        private readonly GetMenuItem $getMenuItem,
        private readonly CreateMenuItem $createMenuItem,
        private readonly UpdateMenuItem $updateMenuItem,
        private readonly DeleteMenuItem $deleteMenuItem,
        private readonly ReorderMenuItems $reorderMenuItems,
        private readonly UploadMenuItemImage $uploadMenuItemImage,
        private readonly DeleteMenuItemImage $deleteMenuItemImage,
        private readonly UploadMenuItemVariantImage $uploadMenuItemVariantImage,
        private readonly DeleteMenuItemVariantImage $deleteMenuItemVariantImage,
        private readonly RestaurantRepositoryInterface $restaurantRepository
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
            'data' => $items->map(fn (MenuItem $item) => $this->menuItemPayload($item, $restaurant)),
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

        return response()->json(['data' => $this->menuItemPayload($menuItem, $restaurant)]);
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
            'availability' => ['nullable', 'array', new OperatingHoursRule()],
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
            'data' => $this->menuItemPayload($menuItem, $restaurant),
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
            'availability' => ['sometimes', 'nullable', 'array', new OperatingHoursRule()],
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
            'data' => $this->menuItemPayload($menuItem, $restaurant),
        ]);
    }

    /**
     * Upload menu item image (simple/combo; one image per item). Multipart: file.
     */
    public function uploadImage(UploadMenuItemImageRequest $request, string $restaurant, string $item): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $menuItem = $this->getMenuItem->handle($request->user(), $restaurant, $item);
        if ($menuItem === null) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        try {
            $menuItem = $this->uploadMenuItemImage->handle($request->user(), $menuItem, $request->file('file'));
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'message' => __('Image updated.'),
            'data' => $this->menuItemPayload($menuItem, $restaurant),
        ]);
    }

    /**
     * Serve menu item image (public; for <img> src).
     * Uses the restaurant item's image when set; otherwise falls back to the catalog source item's image
     * when the restaurant item is linked via source_menu_item_uuid.
     */
    public function serveImage(string $restaurant, string $item): StreamedResponse|JsonResponse
    {
        $restaurantModel = $this->restaurantRepository->findByUuid($restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        $menuItem = MenuItem::query()
            ->where('restaurant_id', $restaurantModel->id)
            ->where('uuid', $item)
            ->with('sourceMenuItem')
            ->first();
        if ($menuItem === null) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        $imagePath = $menuItem->image_path;
        if ($imagePath === null && $menuItem->source_menu_item_uuid !== null && $menuItem->relationLoaded('sourceMenuItem') && $menuItem->sourceMenuItem !== null) {
            $imagePath = $menuItem->sourceMenuItem->image_path;
        }
        if ($imagePath === null) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($imagePath)) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        return $disk->response($imagePath, null, [
            'Content-Type' => $disk->mimeType($imagePath),
        ]);
    }

    /**
     * Delete/clear menu item image.
     */
    public function deleteImage(Request $request, string $restaurant, string $item): JsonResponse|Response
    {
        $menuItem = $this->getMenuItem->handle($request->user(), $restaurant, $item);
        if ($menuItem === null) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        try {
            $menuItem = $this->deleteMenuItemImage->handle($request->user(), $menuItem);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => __('Image removed.'),
            'data' => $this->menuItemPayload($menuItem, $restaurant),
        ]);
    }

    /**
     * Upload variant SKU image (menu items with type with_variants). Multipart: file.
     */
    public function uploadVariantImage(UploadMenuItemImageRequest $request, string $restaurant, string $item, string $sku): JsonResponse|Response
    {
        $menuItem = $this->getMenuItem->handle($request->user(), $restaurant, $item);
        if ($menuItem === null) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        $variantSku = $menuItem->variantSkus()->where('uuid', $sku)->first();
        if ($variantSku === null) {
            return response()->json(['message' => __('Variant not found.')], 404);
        }

        try {
            $variantSku = $this->uploadMenuItemVariantImage->handle($request->user(), $menuItem, $variantSku, $request->file('file'));
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $imageUrl = $baseUrl . '/api/restaurants/' . $restaurant . '/menu-items/' . $item . '/variants/' . $variantSku->uuid . '/image';

        return response()->json([
            'message' => __('Image updated.'),
            'data' => [
                'uuid' => $variantSku->uuid,
                'option_values' => $variantSku->option_values,
                'price' => (float) $variantSku->price,
                'image_url' => $imageUrl,
            ],
        ]);
    }

    /**
     * Serve variant SKU image (public; for <img> src).
     * Uses the restaurant item's variant image when set; otherwise falls back to the catalog source item's
     * variant image when the restaurant item is linked via source_menu_item_uuid (e.g. with_variants from catalog).
     */
    public function serveVariantImage(string $restaurant, string $item, string $sku): StreamedResponse|JsonResponse
    {
        $restaurantModel = $this->restaurantRepository->findByUuid($restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        $menuItem = MenuItem::query()
            ->where('restaurant_id', $restaurantModel->id)
            ->where('uuid', $item)
            ->with(['variantSkus', 'sourceMenuItem.variantSkus'])
            ->first();
        if ($menuItem === null) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        $variantSku = $menuItem->variantSkus->firstWhere('uuid', $sku);
        if ($variantSku === null && $menuItem->source_menu_item_uuid !== null && $menuItem->relationLoaded('sourceMenuItem') && $menuItem->sourceMenuItem !== null) {
            $variantSku = $menuItem->sourceMenuItem->variantSkus->firstWhere('uuid', $sku);
        }
        if ($variantSku === null || ! $variantSku->image_url) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($variantSku->image_url)) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        return $disk->response($variantSku->image_url, null, [
            'Content-Type' => $disk->mimeType($variantSku->image_url),
        ]);
    }

    /**
     * Delete/clear variant SKU image.
     */
    public function deleteVariantImage(Request $request, string $restaurant, string $item, string $sku): JsonResponse|Response
    {
        $menuItem = $this->getMenuItem->handle($request->user(), $restaurant, $item);
        if ($menuItem === null) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        $variantSku = $menuItem->variantSkus()->where('uuid', $sku)->first();
        if ($variantSku === null) {
            return response()->json(['message' => __('Variant not found.')], 404);
        }

        try {
            $variantSku = $this->deleteMenuItemVariantImage->handle($request->user(), $menuItem, $variantSku);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => __('Image removed.'),
            'data' => [
                'uuid' => $variantSku->uuid,
                'option_values' => $variantSku->option_values,
                'price' => (float) $variantSku->price,
                'image_url' => null,
            ],
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
    private function menuItemPayload(MenuItem $item, string $restaurantUuid): array
    {
        $effectiveTranslations = $item->getEffectiveTranslations();
        $tags = $item->relationLoaded('menuItemTags')
            ? $item->menuItemTags->map(fn ($t) => $t->toTagPayload())->values()->all()
            : [];
        $baseUrl = rtrim(config('app.url'), '/');

        $payload = [
            'uuid' => $item->uuid,
            'category_uuid' => $item->category?->uuid,
            'sort_order' => $item->sort_order,
            'is_active' => (bool) $item->is_active,
            'is_available' => (bool) ($item->is_available ?? true),
            'availability' => $item->availability,
            'price' => $item->getEffectivePrice(),
            'translations' => $effectiveTranslations,
            'tags' => $tags,
            'image_url' => $item->image_path
                ? $baseUrl . '/api/restaurants/' . $restaurantUuid . '/menu-items/' . $item->uuid . '/image'
                : null,
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

        if ($item->isWithVariants() && $item->relationLoaded('variantSkus')) {
            $payload['variant_skus'] = $item->variantSkus->map(fn (MenuItemVariantSku $sku) => [
                'uuid' => $sku->uuid,
                'option_values' => $sku->option_values ?? [],
                'price' => (float) $sku->price,
                'image_url' => $sku->image_url
                    ? $baseUrl . '/api/restaurants/' . $restaurantUuid . '/menu-items/' . $item->uuid . '/variants/' . $sku->uuid . '/image'
                    : null,
            ])->values()->all();
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
