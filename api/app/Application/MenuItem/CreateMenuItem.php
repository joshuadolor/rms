<?php

namespace App\Application\MenuItem;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\MenuItem;
use App\Models\User;

final readonly class CreateMenuItem
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array{category_uuid?: string|null, sort_order?: int, source_menu_item_uuid?: string|null, price_override?: float|null, translation_overrides?: array<string, array{name?: string, description?: string|null}>|null, translations?: array<string, array{name: string, description?: string|null}>}  $input
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
        if ($sourceMenuItemUuid !== null && $sourceMenuItemUuid !== '') {
            $source = MenuItem::query()
                ->where('uuid', $sourceMenuItemUuid)
                ->whereNull('restaurant_id')
                ->where('user_id', $user->id)
                ->first();
            if ($source === null) {
                throw new \App\Exceptions\ForbiddenException(__('Catalog menu item not found or you do not own it.'));
            }
        }

        $maxSort = $categoryId
            ? $restaurant->menuItems()->where('category_id', $categoryId)->max('sort_order')
            : $restaurant->menuItems()->whereNull('category_id')->max('sort_order');
        $sortOrder = (int) ($input['sort_order'] ?? $maxSort + 1);

        if ($sourceMenuItemUuid !== null && $sourceMenuItemUuid !== '') {
            $item = $restaurant->menuItems()->create([
                'category_id' => $categoryId,
                'sort_order' => $sortOrder,
                'source_menu_item_uuid' => $sourceMenuItemUuid,
                'price_override' => isset($input['price_override']) ? (float) $input['price_override'] : null,
                'translation_overrides' => $input['translation_overrides'] ?? null,
            ]);
            return $item->load(['sourceMenuItem.translations']);
        }

        $item = $restaurant->menuItems()->create([
            'category_id' => $categoryId,
            'sort_order' => $sortOrder,
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

        return $item->load('translations');
    }
}
