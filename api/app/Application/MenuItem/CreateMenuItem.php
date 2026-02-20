<?php

namespace App\Application\MenuItem;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\MenuItem;
use App\Models\MenuItemTag;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class CreateMenuItem
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array{category_uuid?: string|null, sort_order?: int, source_menu_item_uuid?: string|null, source_variant_uuid?: string|null, price_override?: float|null, translation_overrides?: array<string, array{name?: string, description?: string|null}>|null, translations?: array<string, array{name: string, description?: string|null}>}  $input
     */
    public function handle(User $user, string $restaurantUuid, array $input): ?MenuItem
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null) {
            return null;
        }
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $categoryId = null;
        if (! empty($input['category_uuid'])) {
            $category = \App\Models\Category::query()
                ->whereHas('menu', fn ($q) => $q->where('restaurant_id', $restaurant->id))
                ->where('uuid', $input['category_uuid'])
                ->first();
            if ($category !== null) {
                $categoryId = $category->id;
            }
        }

        $sourceMenuItemUuid = $input['source_menu_item_uuid'] ?? null;
        $sourceVariantUuid = isset($input['source_variant_uuid']) && $input['source_variant_uuid'] !== '' ? (string) $input['source_variant_uuid'] : null;

        $source = null;
        if ($sourceMenuItemUuid !== null && $sourceMenuItemUuid !== '') {
            $source = MenuItem::query()
                ->with('variantSkus')
                ->where('uuid', $sourceMenuItemUuid)
                ->whereNull('restaurant_id')
                ->where('user_id', $user->id)
                ->first();
            if ($source === null) {
                throw new \App\Exceptions\ForbiddenException(__('Catalog menu item not found or you do not own it.'));
            }

            if ($source->type === MenuItem::TYPE_WITH_VARIANTS) {
                if ($sourceVariantUuid === null) {
                    throw ValidationException::withMessages([
                        'source_variant_uuid' => [__('When adding a catalog item with variants, you must specify which variant (source_variant_uuid) to add.')],
                    ]);
                }
                $validVariantUuids = $source->variantSkus->pluck('uuid')->all();
                if (! in_array($sourceVariantUuid, $validVariantUuids, true)) {
                    throw ValidationException::withMessages([
                        'source_variant_uuid' => [__('The selected variant does not belong to this catalog item.')],
                    ]);
                }
            } else {
                if ($sourceVariantUuid !== null) {
                    throw ValidationException::withMessages([
                        'source_variant_uuid' => [__('source_variant_uuid may only be set when the catalog item has type with_variants.')],
                    ]);
                }
            }
        }

        $maxSort = $categoryId
            ? $restaurant->menuItems()->where('category_id', $categoryId)->max('sort_order')
            : $restaurant->menuItems()->whereNull('category_id')->max('sort_order');
        $sortOrder = (int) ($input['sort_order'] ?? $maxSort + 1);

        $availability = array_key_exists('availability', $input) ? $input['availability'] : null;

        if ($sourceMenuItemUuid !== null && $sourceMenuItemUuid !== '') {
            $item = $restaurant->menuItems()->create([
                'category_id' => $categoryId,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'is_available' => true,
                'availability' => $availability,
                'source_menu_item_uuid' => $sourceMenuItemUuid,
                'source_variant_uuid' => $sourceVariantUuid,
                'price_override' => isset($input['price_override']) ? (float) $input['price_override'] : null,
                'translation_overrides' => $input['translation_overrides'] ?? null,
            ]);
            $this->syncTagsIfPresent($user, $item, $input);
            return $item->load(['sourceMenuItem.translations', 'sourceVariantSku', 'menuItemTags']);
        }

        $item = $restaurant->menuItems()->create([
            'category_id' => $categoryId,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'is_available' => true,
            'availability' => $availability,
            'price' => isset($input['price']) ? (float) $input['price'] : null,
        ]);

        $inputTranslations = $input['translations'] ?? [];
        $defaultLocale = $restaurant->default_locale ?? 'en';
        $installedLocales = $restaurant->languages()->pluck('locale')->all();
        $defaultData = $inputTranslations[$defaultLocale] ?? [];

        foreach ($installedLocales as $locale) {
            $data = $inputTranslations[$locale] ?? $defaultData;
            $name = isset($data['name']) ? (string) $data['name'] : '';
            $description = array_key_exists('description', $data) ? $data['description'] : null;
            $item->translations()->create([
                'locale' => $locale,
                'name' => $name,
                'description' => $description,
            ]);
        }

        $this->syncTagsIfPresent($user, $item, $input);

        return $item->load(['translations', 'menuItemTags']);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function syncTagsIfPresent(User $user, MenuItem $item, array $input): void
    {
        $tagUuids = $input['tag_uuids'] ?? null;
        if (! is_array($tagUuids)) {
            return;
        }
        $tagUuids = array_values(array_filter(array_map('strval', $tagUuids)));
        $tagIds = MenuItemTag::validateAndResolveIdsForUser($user, $tagUuids);
        $item->menuItemTags()->sync($tagIds);
    }
}
