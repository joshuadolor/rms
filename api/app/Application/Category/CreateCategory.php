<?php

namespace App\Application\Category;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Category;
use App\Models\User;

final readonly class CreateCategory
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array{sort_order?: int, translations?: array<string, array{name: string}>}  $input
     */
    public function handle(User $user, string $restaurantUuid, string $menuUuid, array $input): ?Category
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

        $sortOrder = array_key_exists('sort_order', $input)
            ? (int) $input['sort_order']
            : (int) $menu->categories()->max('sort_order') + 1;

        $category = $menu->categories()->create(['sort_order' => $sortOrder]);

        $translations = $input['translations'] ?? [];
        $installedLocales = $restaurant->languages()->pluck('locale')->all();
        $defaultLocale = $restaurant->default_locale ?? 'en';
        $defaultData = $translations[$defaultLocale] ?? ['name' => ''];

        foreach ($installedLocales as $locale) {
            $data = $translations[$locale] ?? $defaultData;
            $name = isset($data['name']) ? (string) $data['name'] : '';
            $category->translations()->create([
                'locale' => $locale,
                'name' => $name,
            ]);
        }

        return $category->load('translations');
    }
}
