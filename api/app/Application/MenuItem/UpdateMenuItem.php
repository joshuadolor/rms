<?php

namespace App\Application\MenuItem;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\MenuItem;
use App\Models\MenuItemTag;
use App\Models\User;

final readonly class UpdateMenuItem
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array{sort_order?: int, category_uuid?: string|null, is_active?: bool, is_available?: bool, translations?: array<string, array{name?: string, description?: string|null}>, price?: float|null, price_override?: float|null, translation_overrides?: array<string, array{name?: string, description?: string|null}>|null, revert_to_base?: bool}  $input
     */
    public function handle(User $user, string $restaurantUuid, string $itemUuid, array $input): ?MenuItem
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null) {
            return null;
        }
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $item = MenuItem::query()->where('uuid', $itemUuid)->where('restaurant_id', $restaurant->id)->first();
        if ($item === null) {
            return null;
        }

        $update = [];
        if (array_key_exists('sort_order', $input)) {
            $update['sort_order'] = (int) $input['sort_order'];
        }
        if (array_key_exists('is_active', $input)) {
            $update['is_active'] = (bool) $input['is_active'];
        }
        if (array_key_exists('is_available', $input)) {
            $update['is_available'] = (bool) $input['is_available'];
        }
        if (array_key_exists('availability', $input)) {
            $update['availability'] = $input['availability'];
        }
        if (array_key_exists('category_uuid', $input)) {
            $categoryId = null;
            if ($input['category_uuid'] !== null && $input['category_uuid'] !== '') {
                $category = \App\Models\Category::query()
                    ->whereHas('menu', fn ($q) => $q->where('restaurant_id', $restaurant->id))
                    ->where('uuid', $input['category_uuid'])
                    ->first();
                if ($category !== null) {
                    $categoryId = $category->id;
                }
            }
            $update['category_id'] = $categoryId;
        }

        if ($item->source_menu_item_uuid !== null) {
            if (! empty($input['revert_to_base'])) {
                $update['price_override'] = null;
                $update['translation_overrides'] = null;
            } else {
                if (array_key_exists('price_override', $input)) {
                    $update['price_override'] = $input['price_override'] === null ? null : (float) $input['price_override'];
                }
                if (array_key_exists('translation_overrides', $input)) {
                    $update['translation_overrides'] = $input['translation_overrides'];
                }
            }
        } else {
            if (array_key_exists('price', $input)) {
                $update['price'] = $input['price'] === null ? null : (float) $input['price'];
            }
        }

        if ($update !== []) {
            $item->update($update);
        }

        if ($item->source_menu_item_uuid === null) {
            $translations = $input['translations'] ?? [];
            foreach ($translations as $locale => $data) {
                if (! $restaurant->languages()->where('locale', $locale)->exists()) {
                    continue;
                }
                $existing = $item->translations()->where('locale', $locale)->first();
                $name = array_key_exists('name', $data) ? (string) $data['name'] : ($existing?->name ?? '');
                $description = array_key_exists('description', $data) ? $data['description'] : ($existing?->description ?? null);
                $item->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $name, 'description' => $description]
                );
            }
        }

        if (array_key_exists('tag_uuids', $input)) {
            $tagUuids = is_array($input['tag_uuids']) ? array_values(array_filter(array_map('strval', $input['tag_uuids']))) : [];
            $tagIds = MenuItemTag::validateAndResolveIdsForUser($user, $tagUuids);
            $item->menuItemTags()->sync($tagIds);
        }

        return $item->fresh(['translations', 'sourceMenuItem.translations', 'sourceVariantSku', 'menuItemTags']);
    }
}
