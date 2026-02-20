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

        $categoryData = ['sort_order' => $sortOrder];
        if (array_key_exists('availability', $input)) {
            $categoryData['availability'] = $input['availability'];
        }
        $category = $menu->categories()->create($categoryData);

        $translations = $input['translations'] ?? [];
        $installedLocales = $restaurant->languages()->pluck('locale')->all();
        $defaultLocale = $restaurant->default_locale ?? 'en';
        $defaultData = $translations[$defaultLocale] ?? ['name' => '', 'description' => null];

        foreach ($installedLocales as $locale) {
            $data = $translations[$locale] ?? $defaultData;
            $name = isset($data['name']) ? (string) $data['name'] : '';
            $description = array_key_exists('description', $data) ? $data['description'] : null;
            $category->translations()->create([
                'locale' => $locale,
                'name' => $name,
                'description' => $description !== null ? (string) $description : null,
            ]);
        }

        return $category->load('translations');
    }
}
