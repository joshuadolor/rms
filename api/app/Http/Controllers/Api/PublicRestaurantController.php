<?php

namespace App\Http\Controllers\Api;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (no auth) restaurant page by slug. For the generic [slug].domain.com page.
 */
class PublicRestaurantController extends Controller
{
    /** Menu item types allowed on the public API. Null is treated as simple. */
    private const PUBLIC_MENU_ITEM_TYPES = [
        MenuItem::TYPE_SIMPLE,
        MenuItem::TYPE_COMBO,
        MenuItem::TYPE_WITH_VARIANTS,
    ];

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
        // Fallback: if current locale has no description, use default locale's so the About section can show content
        if (($description === null || trim((string) $description) === '') && $locale !== ($restaurant->default_locale ?? 'en')) {
            $defaultTranslation = $restaurant->translations()->where('locale', $restaurant->default_locale ?? 'en')->first();
            $description = $defaultTranslation?->description ?? $description;
        }
        if ($description === null || trim((string) $description) === '') {
            $firstWithDescription = $restaurant->translations()->whereNotNull('description')->where('description', '!=', '')->first();
            $description = $firstWithDescription?->description ?? $description;
        }

        // Eager-load relations required for public payload: combo breakdown (combo_entries), variant data, and item/category images.
        // When item is from catalog (source_menu_item_uuid), source's type and combo_entries are used for combo display.
        $menuItems = $this->queryPublicMenuItems($restaurant)
            ->with([
                'translations',
                'sourceMenuItem.translations',
                'sourceMenuItem.variantOptionGroups',
                'sourceMenuItem.variantSkus',
                'sourceMenuItem.comboEntries.referencedMenuItem.translations',
                'sourceMenuItem.comboEntries.variant',
                'sourceVariantSku',
                'menuItemTags',
                'comboEntries.referencedMenuItem.translations',
                'comboEntries.variant',
                'variantOptionGroups',
                'variantSkus',
            ])
            ->orderBy('sort_order')->orderBy('id')->get();
        $restaurantForPayload = $restaurant;
        $menuPayload = $menuItems->map(fn ($item) => $this->buildPublicMenuItemPayload($item, $locale, true, $restaurantForPayload))
            ->all();

        $menuGroups = $this->buildMenuGroupsForLocale($restaurant, $locale);

        $approvedFeedbacks = $restaurant->feedbacks()
            ->approved()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($f) => [
                'uuid' => $f->uuid,
                'rating' => $f->rating,
                'text' => $f->text,
                'name' => $f->name,
                'created_at' => $f->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $activeContacts = $restaurant->contacts()
            ->active()
            ->orderBy('id')
            ->get()
            ->map(function ($c) {
                $value = $c->getEffectiveValue();
                $isPhone = in_array($c->type, RestaurantContact::TYPES_PHONE, true);
                return [
                    'uuid' => $c->uuid,
                    'type' => $c->type,
                    'value' => $value,
                    'number' => $isPhone ? $value : null,
                    'label' => $c->label,
                ];
            })
            ->values()
            ->all();

        $viewer = $request->user('sanctum');
        $isOwnerViewer = $viewer !== null && $restaurant->isOwnedBy($viewer);
        $ownerAdminUrl = $isOwnerViewer ? $this->buildOwnerAdminUrl($restaurant->uuid) : null;
        $needsData = $isOwnerViewer ? $this->computeNeedsData($menuGroups, $description) : false;

        return response()->json([
            'data' => [
                'name' => $restaurant->name,
                'tagline' => $restaurant->tagline,
                'primary_color' => $restaurant->primary_color,
                'slug' => $restaurant->slug,
                'template' => $this->resolveTemplate($restaurant->template ?? 'template-1'),
                'year_established' => $restaurant->year_established !== null ? (int) $restaurant->year_established : null,
                'logo_url' => $restaurant->logo_path
                    ? $baseUrl . '/api/restaurants/' . $restaurant->uuid . '/logo'
                    : null,
                'banner_url' => $restaurant->banner_path
                    ? $baseUrl . '/api/restaurants/' . $restaurant->uuid . '/banner'
                    : null,
                'default_locale' => $restaurant->default_locale ?? 'en',
                'currency' => $restaurant->currency ?? 'USD',
                'operating_hours' => $restaurant->operating_hours,
                'languages' => $installedLocales,
                'locale' => $locale,
                'description' => $description,
                'menu_items' => $menuPayload,
                'menu_groups' => $menuGroups,
                'feedbacks' => $approvedFeedbacks,
                'contacts' => $activeContacts,
                'viewer' => [
                    'is_owner' => $isOwnerViewer,
                    'owner_admin_url' => $ownerAdminUrl,
                    'needs_data' => $needsData,
                ],
            ],
        ]);
    }

    /**
     * Whether the public site "needs data" for owner context: no menu content (no groups or no items)
     * and/or no description. Only meaningful when the viewer is the owner; callers should pass
     * this only when is_owner is true, otherwise return false.
     *
     * @param  array<int, array{items: array}>  $menuGroups
     */
    private function computeNeedsData(array $menuGroups, ?string $description): bool
    {
        $hasMenuContent = false;
        foreach ($menuGroups as $group) {
            $items = $group['items'] ?? [];
            if ($items !== []) {
                $hasMenuContent = true;
                break;
            }
        }
        $hasDescription = $description !== null && trim($description) !== '';

        return ! $hasMenuContent || ! $hasDescription;
    }

    /**
     * Build the owner-facing admin URL for this restaurant on the frontend app.
     */
    private function buildOwnerAdminUrl(string $restaurantUuid): string
    {
        $frontendUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');

        return $frontendUrl . '/app/restaurants/' . $restaurantUuid . '?tab=profile';
    }

    /** Map legacy or invalid template to a valid one; valid: template-1, template-2. API never returns "default" or "minimal". */
    private function resolveTemplate(?string $template): string
    {
        $map = ['default' => 'template-1', 'minimal' => 'template-2'];
        $resolved = $map[$template] ?? $template;
        $ids = config('templates.ids', Restaurant::TEMPLATES);

        return in_array($resolved, $ids, true) ? $resolved : 'template-1';
    }

    /**
     * Query menu items allowed on the public API: active, in active category or uncategorized,
     * and type is simple, combo, or with_variants (null treated as simple).
     */
    private function queryPublicMenuItems(Restaurant $restaurant): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $restaurant->menuItems()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('category_id')
                    ->orWhereHas('category', fn ($c) => $c->where('is_active', true));
            })
            ->where(function ($q) {
                $q->whereIn('type', self::PUBLIC_MENU_ITEM_TYPES)
                    ->orWhereNull('type');
            });
    }

    /**
     * Build a single public menu item payload (no internal id). For "ending variant" items
     * (source_variant_uuid set) we expose type as 'simple' and do not include variant blocks.
     *
     * @param  bool  $includeSortOrder  When true (flat menu_items), include sort_order.
     * @param  Restaurant|null  $restaurant  When set, used to build image_url (item and variant images).
     * @return array{uuid: string, type: string, name: string, description: string|null, price: float|null, ...}
     */
    private function buildPublicMenuItemPayload(MenuItem $item, string $locale, bool $includeSortOrder = false, ?Restaurant $restaurant = null): array
    {
        $effective = $item->getEffectiveTranslations();
        $t = $effective[$locale] ?? reset($effective) ?: ['name' => '', 'description' => null];
        $tags = $item->menuItemTags->map(fn ($tag) => $tag->toTagPayload())->values()->all();

        $publicType = $this->getPublicMenuItemType($item);

        $baseUrl = rtrim(config('app.url'), '/');
        $restaurantUuid = $restaurant !== null ? $restaurant->uuid : null;
        $itemImageUrl = null;
        if ($restaurantUuid !== null) {
            if ($item->image_path) {
                $itemImageUrl = $baseUrl . '/api/restaurants/' . $restaurantUuid . '/menu-items/' . $item->uuid . '/image';
            } elseif ($item->source_menu_item_uuid !== null && $item->relationLoaded('sourceMenuItem') && $item->sourceMenuItem !== null && $item->sourceMenuItem->image_path) {
                $itemImageUrl = $baseUrl . '/api/restaurants/' . $restaurantUuid . '/menu-items/' . $item->uuid . '/image';
            }
        }
        $payload = [
            'uuid' => $item->uuid,
            'type' => $publicType,
            'name' => $t['name'] ?? '',
            'description' => $t['description'] ?? null,
            'price' => $item->getEffectivePrice(),
            'is_available' => (bool) ($item->is_available ?? true),
            'availability' => $item->availability,
            'tags' => $tags,
            'image_url' => $itemImageUrl,
        ];
        if ($includeSortOrder) {
            $payload['sort_order'] = $item->sort_order;
        }

        // Combo items must include combo_entries (array). Use item's own entries, or source catalog's when item is from catalog.
        if ($publicType === MenuItem::TYPE_COMBO) {
            $entries = $this->getComboEntriesForPublic($item);
            $payload['combo_entries'] = $entries !== null
                ? $this->buildPublicComboEntries($entries, $locale)
                : [];
        }

        if ($publicType === MenuItem::TYPE_WITH_VARIANTS) {
            $variantData = $this->resolveVariantDataForPublic($item, $restaurant);
            if ($variantData !== null) {
                $payload['variant_option_groups'] = $variantData['variant_option_groups'];
                $payload['variant_skus'] = $variantData['variant_skus'];
            }
        }

        return $payload;
    }

    /** Public type: 'simple' for ending variant (single SKU); when item is from catalog (no variant), use source's type so combo shows as combo. */
    private function getPublicMenuItemType(MenuItem $item): string
    {
        if ($item->source_variant_uuid !== null) {
            return MenuItem::TYPE_SIMPLE;
        }
        if ($item->source_menu_item_uuid !== null && $item->relationLoaded('sourceMenuItem') && $item->sourceMenuItem) {
            $type = $item->sourceMenuItem->type ?? $item->type ?? MenuItem::TYPE_SIMPLE;
        } else {
            $type = $item->type ?? MenuItem::TYPE_SIMPLE;
        }

        return in_array($type, self::PUBLIC_MENU_ITEM_TYPES, true) ? $type : MenuItem::TYPE_SIMPLE;
    }

    /**
     * Entries to use for combo_entries in public payload: item's own comboEntries, or source catalog's when item is from catalog.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\ComboEntry>|null
     */
    private function getComboEntriesForPublic(MenuItem $item): ?\Illuminate\Support\Collection
    {
        if ($item->relationLoaded('comboEntries') && $item->comboEntries->isNotEmpty()) {
            return $item->comboEntries;
        }
        if ($item->source_menu_item_uuid !== null && $item->relationLoaded('sourceMenuItem') && $item->sourceMenuItem
            && ($item->sourceMenuItem->type ?? MenuItem::TYPE_SIMPLE) === MenuItem::TYPE_COMBO
            && $item->sourceMenuItem->relationLoaded('comboEntries')) {
            return $item->sourceMenuItem->comboEntries;
        }

        return null;
    }

    /**
     * Build public-safe combo_entries: referenced_item_uuid, name (locale), quantity, modifier_label, variant_uuid.
     * No internal id in any field.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\ComboEntry>  $entries
     * @return array<int, array{referenced_item_uuid: string, name: string, quantity: int, modifier_label: string|null, variant_uuid: string|null}>
     */
    private function buildPublicComboEntries($entries, string $locale): array
    {
        return $entries->map(function ($entry) use ($locale) {
            $ref = $entry->referencedMenuItem;
            $name = '';
            $referencedItemUuid = null;
            if ($ref !== null) {
                $referencedItemUuid = $ref->uuid;
                $effective = $ref->getEffectiveTranslations();
                $t = $effective[$locale] ?? reset($effective);
                $name = $t['name'] ?? '';
                if ($entry->variant_id !== null && $entry->relationLoaded('variant') && $entry->variant) {
                    $values = $entry->variant->option_values;
                    if (is_array($values) && $values !== []) {
                        $name = $name . ' - ' . implode(', ', array_values($values));
                    }
                }
            }
            return [
                'referenced_item_uuid' => $referencedItemUuid,
                'name' => $name,
                'quantity' => (int) $entry->quantity,
                'modifier_label' => $entry->modifier_label,
            ] + ($entry->variant_id !== null && $entry->relationLoaded('variant') && $entry->variant
                ? ['variant_uuid' => $entry->variant->uuid]
                : ['variant_uuid' => null]);
        })->values()->all();
    }

    /**
     * Resolve variant_option_groups and variant_skus for public payload. Uses item's own data,
     * or source catalog item's when this item is a restaurant usage of a with_variants catalog item.
     * When restaurant is provided, variant image_url is the full serve URL; the serve endpoint
     * returns the restaurant variant's image or the catalog source variant's image when the item
     * has no own variant image (catalog fallback).
     *
     * @return array{variant_option_groups: array, variant_skus: array}|null
     */
    private function resolveVariantDataForPublic(MenuItem $item, ?Restaurant $restaurant = null): ?array
    {
        $groups = $item->variantOptionGroups;
        $skus = $item->variantSkus;
        if (($groups === null || $groups->isEmpty()) && ($skus === null || $skus->isEmpty())
            && $item->source_menu_item_uuid !== null
            && $item->relationLoaded('sourceMenuItem')
            && $item->sourceMenuItem !== null
            && $item->sourceMenuItem->isWithVariants()) {
            $groups = $item->sourceMenuItem->variantOptionGroups;
            $skus = $item->sourceMenuItem->variantSkus;
        }
        if (($groups === null || $groups->isEmpty()) && ($skus === null || $skus->isEmpty())) {
            return null;
        }
        $baseUrl = rtrim(config('app.url'), '/');
        $restaurantUuid = $restaurant?->uuid;
        $variantSkus = ($skus ?? collect())->map(function ($sku) use ($baseUrl, $restaurantUuid, $item) {
            $path = $sku->image_url;
            if ($path === null && $item->source_menu_item_uuid !== null && $item->relationLoaded('sourceMenuItem') && $item->sourceMenuItem !== null) {
                $sourceSku = $item->sourceMenuItem->variantSkus?->firstWhere('uuid', $sku->uuid);
                $path = $sourceSku?->image_url;
            }
            $imageUrl = null;
            if ($path !== null && $restaurantUuid !== null) {
                $imageUrl = $baseUrl . '/api/restaurants/' . $restaurantUuid . '/menu-items/' . $item->uuid . '/variants/' . $sku->uuid . '/image';
            }

            return [
                'uuid' => $sku->uuid,
                'option_values' => $sku->option_values ?? [],
                'price' => (float) $sku->price,
                'image_url' => $imageUrl,
            ];
        })->values()->all();
        $variantOptionGroups = ($groups ?? collect())->map(fn ($g) => [
            'name' => $g->name,
            'values' => $g->values ?? [],
        ])->values()->all();

        return [
            'variant_option_groups' => $variantOptionGroups,
            'variant_skus' => $variantSkus,
        ];
    }

    /**
     * Menu items grouped by category (same shape as Blade buildMenuGroupsForLocale).
     * Categories sorted by sort_order; uncategorized as "Menu".
     * Only includes items allowed on public API (simple, combo, with_variants).
     *
     * @return array<int, array{category_name: string, category_uuid: string|null, availability: array|null, items: array}>
     */
    private function buildMenuGroupsForLocale(Restaurant $restaurant, string $locale): array
    {
        // Same relations as flat menu_items; category.menu required for category image_url; source comboEntries for catalog combos.
        $items = $this->queryPublicMenuItems($restaurant)
            ->with([
                'translations',
                'sourceMenuItem.translations',
                'sourceMenuItem.variantOptionGroups',
                'sourceMenuItem.variantSkus',
                'sourceMenuItem.comboEntries.referencedMenuItem.translations',
                'sourceMenuItem.comboEntries.variant',
                'sourceVariantSku',
                'menuItemTags',
                'category.translations',
                'category.menu',
                'comboEntries.referencedMenuItem.translations',
                'comboEntries.variant',
                'variantOptionGroups',
                'variantSkus',
            ])
            ->orderBy('sort_order')->orderBy('id')->get();

        $baseUrl = rtrim(config('app.url'), '/');
        $restaurantUuid = $restaurant->uuid;
        $byCategory = [];
        foreach ($items as $item) {
            $payload = $this->buildPublicMenuItemPayload($item, $locale, false, $restaurant);
            $catUuid = null;
            $catName = __('Menu');
            $catSort = 9999;
            $categoryImageUrl = null;
            if ($item->category_id !== null && $item->relationLoaded('category') && $item->category) {
                $category = $item->category;
                $catUuid = $category->uuid;
                $catSort = (int) ($category->sort_order ?? 0);
                $catTrans = $category->translations->firstWhere('locale', $locale)
                    ?? $category->translations->first();
                $catName = $catTrans?->name ?? __('Menu');
                // category.menu required for category image_url (serve path uses menu uuid).
                if ($category->image_path && $category->relationLoaded('menu') && $category->menu) {
                    $categoryImageUrl = $baseUrl . '/api/restaurants/' . $restaurantUuid . '/menus/' . $category->menu->uuid . '/categories/' . $category->uuid . '/image';
                }
            }
            $key = $catUuid ?? 'uncategorized';
            if (! isset($byCategory[$key])) {
                $groupAvailability = ($item->category_id !== null && $item->category) ? $item->category->availability : null;
                $byCategory[$key] = [
                    'category_uuid' => $catUuid,
                    'category_name' => $catName,
                    'category_sort_order' => $catSort,
                    'availability' => $groupAvailability,
                    'image_url' => $categoryImageUrl,
                    'items' => [],
                ];
            }
            $byCategory[$key]['items'][] = $payload;
        }
        uasort($byCategory, fn ($a, $b) => $a['category_sort_order'] <=> $b['category_sort_order']);

        return array_values(array_map(fn ($g) => [
            'category_name' => $g['category_name'],
            'category_uuid' => $g['category_uuid'],
            'availability' => $g['availability'],
            'image_url' => $g['image_url'] ?? null,
            'items' => $g['items'],
        ], $byCategory));
    }
}
