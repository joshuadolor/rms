<?php

namespace App\Http\Controllers\Api;

use App\Application\MenuItem\CreateStandaloneMenuItem;
use App\Application\MenuItem\DeleteUserMenuItem;
use App\Application\MenuItem\DeleteUserMenuItemImage;
use App\Application\MenuItem\DeleteUserMenuItemVariantImage;
use App\Application\MenuItem\GetUserMenuItem;
use App\Application\MenuItem\ListUserMenuItems;
use App\Application\MenuItem\UpdateUserMenuItem;
use App\Application\MenuItem\UploadUserMenuItemImage;
use App\Application\MenuItem\UploadUserMenuItemVariantImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadMenuItemImageRequest;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserMenuItemController extends Controller
{
    public function __construct(
        private readonly ListUserMenuItems $listUserMenuItems,
        private readonly GetUserMenuItem $getUserMenuItem,
        private readonly CreateStandaloneMenuItem $createStandaloneMenuItem,
        private readonly UpdateUserMenuItem $updateUserMenuItem,
        private readonly DeleteUserMenuItem $deleteUserMenuItem,
        private readonly UploadUserMenuItemImage $uploadUserMenuItemImage,
        private readonly DeleteUserMenuItemImage $deleteUserMenuItemImage,
        private readonly UploadUserMenuItemVariantImage $uploadUserMenuItemVariantImage,
        private readonly DeleteUserMenuItemVariantImage $deleteUserMenuItemVariantImage
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
            'type' => ['nullable', 'string', 'in:simple,combo,with_variants'],
            'combo_price' => ['nullable', 'numeric', 'min:0'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
            'combo_entries' => ['nullable', 'array'],
            'combo_entries.*.menu_item_uuid' => ['required_with:combo_entries', 'uuid'],
            'combo_entries.*.variant_uuid' => ['nullable', 'uuid'],
            'combo_entries.*.quantity' => ['nullable', 'integer', 'min:1'],
            'combo_entries.*.modifier_label' => ['nullable', 'string', 'max:255'],
            'variant_option_groups' => ['nullable', 'array'],
            'variant_option_groups.*.name' => ['required_with:variant_option_groups', 'string', 'max:255'],
            'variant_option_groups.*.values' => ['required_with:variant_option_groups', 'array', 'min:1'],
            'variant_option_groups.*.values.*' => ['string', 'max:255'],
            'variant_skus' => ['nullable', 'array'],
            'variant_skus.*.option_values' => ['required_with:variant_skus', 'array'],
            'variant_skus.*.price' => ['required_with:variant_skus', 'numeric', 'min:0'],
            'variant_skus.*.image_url' => ['nullable', 'string', 'max:2000'],
        ]);

        $translations = $validated['translations'];
        $hasName = collect($translations)->contains(fn ($t) => ! empty(trim($t['name'] ?? '')));
        if (! $hasName) {
            return response()->json([
                'message' => __('At least one translation must have a name.'),
                'errors' => ['translations' => [__('Name is required for at least one language.')]],
            ], 422);
        }

        try {
            $menuItem = $this->createStandaloneMenuItem->handle($request->user(), $validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

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
            'type' => ['nullable', 'string', 'in:simple,combo,with_variants'],
            'combo_price' => ['nullable', 'numeric', 'min:0'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
            'combo_entries' => ['nullable', 'array'],
            'combo_entries.*.menu_item_uuid' => ['required_with:combo_entries', 'uuid'],
            'combo_entries.*.variant_uuid' => ['nullable', 'uuid'],
            'combo_entries.*.quantity' => ['nullable', 'integer', 'min:1'],
            'combo_entries.*.modifier_label' => ['nullable', 'string', 'max:255'],
            'variant_option_groups' => ['nullable', 'array'],
            'variant_option_groups.*.name' => ['required_with:variant_option_groups', 'string', 'max:255'],
            'variant_option_groups.*.values' => ['required_with:variant_option_groups', 'array', 'min:1'],
            'variant_option_groups.*.values.*' => ['string', 'max:255'],
            'variant_skus' => ['nullable', 'array'],
            'variant_skus.*.option_values' => ['required_with:variant_skus', 'array'],
            'variant_skus.*.price' => ['required_with:variant_skus', 'numeric', 'min:0'],
            'variant_skus.*.image_url' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $menuItem = $this->updateUserMenuItem->handle($request->user(), $item, $validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
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
     * Upload image for a standalone (catalog) menu item. Only available for menu items in the catalog context.
     */
    public function uploadImage(UploadMenuItemImageRequest $request, string $item): JsonResponse|Response
    {
        $menuItem = $this->getUserMenuItem->handle($request->user(), $item);
        if ($menuItem === null || ! $menuItem->isStandalone()) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        try {
            $menuItem = $this->uploadUserMenuItemImage->handle($request->user(), $menuItem, $request->file('file'));
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'message' => __('Image updated.'),
            'data' => $this->menuItemPayload($menuItem),
        ]);
    }

    /**
     * Serve catalog menu item image (public; for <img> src).
     */
    public function serveImage(string $item): StreamedResponse|JsonResponse
    {
        $menuItem = MenuItem::query()
            ->whereNull('restaurant_id')
            ->where('uuid', $item)
            ->first();
        if ($menuItem === null || ! $menuItem->image_path) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($menuItem->image_path)) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        return $disk->response($menuItem->image_path, null, [
            'Content-Type' => $disk->mimeType($menuItem->image_path),
        ]);
    }

    /**
     * Delete image for a standalone menu item.
     */
    public function deleteImage(Request $request, string $item): JsonResponse|Response
    {
        $menuItem = $this->getUserMenuItem->handle($request->user(), $item);
        if ($menuItem === null || ! $menuItem->isStandalone()) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        try {
            $menuItem = $this->deleteUserMenuItemImage->handle($request->user(), $menuItem);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => __('Image removed.'),
            'data' => $this->menuItemPayload($menuItem),
        ]);
    }

    /**
     * Upload variant SKU image for a standalone menu item (type with_variants).
     */
    public function uploadVariantImage(UploadMenuItemImageRequest $request, string $item, string $sku): JsonResponse|Response
    {
        $menuItem = $this->getUserMenuItem->handle($request->user(), $item);
        if ($menuItem === null || ! $menuItem->isStandalone()) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        $variantSku = $menuItem->variantSkus()->where('uuid', $sku)->first();
        if ($variantSku === null) {
            return response()->json(['message' => __('Variant not found.')], 404);
        }

        try {
            $variantSku = $this->uploadUserMenuItemVariantImage->handle($request->user(), $menuItem, $variantSku, $request->file('file'));
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $imageUrl = $baseUrl . '/api/menu-items/' . $item . '/variants/' . $variantSku->uuid . '/image';

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
     * Serve catalog menu item variant image (public; for <img> src).
     */
    public function serveVariantImage(string $item, string $sku): StreamedResponse|JsonResponse
    {
        $menuItem = MenuItem::query()
            ->whereNull('restaurant_id')
            ->where('uuid', $item)
            ->first();
        if ($menuItem === null) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        $variantSku = $menuItem->variantSkus()->where('uuid', $sku)->first();
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
     * Delete variant SKU image for a standalone menu item.
     */
    public function deleteVariantImage(Request $request, string $item, string $sku): JsonResponse|Response
    {
        $menuItem = $this->getUserMenuItem->handle($request->user(), $item);
        if ($menuItem === null || ! $menuItem->isStandalone()) {
            return response()->json(['message' => __('Menu item not found.')], 404);
        }

        $variantSku = $menuItem->variantSkus()->where('uuid', $sku)->first();
        if ($variantSku === null) {
            return response()->json(['message' => __('Variant not found.')], 404);
        }

        try {
            $variantSku = $this->deleteUserMenuItemVariantImage->handle($request->user(), $menuItem, $variantSku);
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

        $type = $item->type ?? MenuItem::TYPE_SIMPLE;
        $baseUrl = $item->isStandalone() ? rtrim(config('app.url'), '/') : null;
        $payload = [
            'uuid' => $item->uuid,
            'category_uuid' => $item->category?->uuid,
            'sort_order' => $item->sort_order,
            'type' => $type,
            'price' => $item->getEffectivePrice(),
            'image_url' => $item->isStandalone() && $item->image_path
                ? $baseUrl . '/api/menu-items/' . $item->uuid . '/image'
                : null,
            'translations' => $translations,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];

        if ($type === MenuItem::TYPE_COMBO && $item->relationLoaded('comboEntries')) {
            $payload['combo_price'] = $item->combo_price !== null ? (float) $item->combo_price : null;
            $payload['combo_entries'] = $item->comboEntries->map(function ($entry) {
                $arr = [
                    'menu_item_uuid' => $entry->referencedMenuItem?->uuid,
                    'quantity' => $entry->quantity,
                    'modifier_label' => $entry->modifier_label,
                ];
                if ($entry->variant_id !== null && $entry->relationLoaded('variant')) {
                    $arr['variant_uuid'] = $entry->variant?->uuid;
                } else {
                    $arr['variant_uuid'] = null;
                }
                return $arr;
            })->values()->all();
        }

        if ($type === MenuItem::TYPE_WITH_VARIANTS && $item->relationLoaded('variantOptionGroups') && $item->relationLoaded('variantSkus')) {
            $payload['variant_option_groups'] = $item->variantOptionGroups->map(fn ($g) => [
                'name' => $g->name,
                'values' => $g->values,
            ])->values()->all();
            $payload['variant_skus'] = $item->variantSkus->map(function ($sku) use ($item, $baseUrl) {
                $imageUrl = null;
                if ($sku->image_url && $baseUrl !== null) {
                    $imageUrl = $baseUrl . '/api/menu-items/' . $item->uuid . '/variants/' . $sku->uuid . '/image';
                }
                return [
                    'uuid' => $sku->uuid,
                    'option_values' => $sku->option_values,
                    'price' => (float) $sku->price,
                    'image_url' => $imageUrl,
                ];
            })->values()->all();
        }

        if ($item->relationLoaded('restaurant')) {
            $payload['restaurant_uuid'] = $item->restaurant?->uuid;
        }

        return $payload;
    }
}
