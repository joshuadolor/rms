<?php

namespace App\Application\Category;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Category;
use App\Models\User;

final readonly class UpdateCategory
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array{sort_order?: int, translations?: array<string, array{name?: string}>}  $input
     */
    public function handle(User $user, string $restaurantUuid, string $menuUuid, string $categoryUuid, array $input): ?Category
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null) {
            return null;
        }
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $menu = $restaurant->menus()->where('uuid', $menuUuid)->first();
        if ($menu === null) {
            return null;
        }

        $category = Category::query()
            ->where('uuid', $categoryUuid)
            ->where('menu_id', $menu->id)
            ->first();

        if ($category === null) {
            return null;
        }

        $update = [];
        if (array_key_exists('sort_order', $input)) {
            $update['sort_order'] = (int) $input['sort_order'];
        }
        if (array_key_exists('is_active', $input)) {
            $update['is_active'] = (bool) $input['is_active'];
        }
        if ($update !== []) {
            $category->update($update);
        }

        $translations = $input['translations'] ?? [];
        foreach ($translations as $locale => $data) {
            if (! $restaurant->languages()->where('locale', $locale)->exists()) {
                continue;
            }
            $name = array_key_exists('name', $data) ? (string) $data['name'] : '';
            $category->translations()->updateOrCreate(
                ['locale' => $locale],
                ['name' => $name]
            );
        }

        return $category->fresh(['translations']);
    }
}
