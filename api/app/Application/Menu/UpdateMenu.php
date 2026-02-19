<?php

namespace App\Application\Menu;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Menu;
use App\Models\User;

final readonly class UpdateMenu
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array{name?: string|null, is_active?: bool, sort_order?: int, translations?: array<string, array{name?: string, description?: string|null}>}  $input
     */
    public function handle(User $user, string $restaurantUuid, string $menuUuid, array $input): ?Menu
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null) {
            return null;
        }
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $menu = Menu::query()
            ->where('uuid', $menuUuid)
            ->where('restaurant_id', $restaurant->id)
            ->with('translations')
            ->first();

        if ($menu === null) {
            return null;
        }

        $update = [];
        if (array_key_exists('is_active', $input)) {
            $update['is_active'] = (bool) $input['is_active'];
        }
        if (array_key_exists('sort_order', $input)) {
            $update['sort_order'] = (int) $input['sort_order'];
        }

        $defaultLocale = $restaurant->default_locale ?? 'en';
        $translationsInput = $input['translations'] ?? [];
        $installedLocales = $restaurant->languages()->pluck('locale')->all();

        if ($translationsInput !== []) {
            foreach ($translationsInput as $locale => $data) {
                if (! in_array($locale, $installedLocales, true)) {
                    continue;
                }
                $name = array_key_exists('name', $data) ? (string) $data['name'] : '';
                $description = array_key_exists('description', $data) ? $data['description'] : null;
                $menu->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'name' => $name,
                        'description' => $description !== null ? (string) $description : null,
                    ]
                );
            }
            $first = $translationsInput[$defaultLocale] ?? reset($translationsInput);
            $update['name'] = isset($first['name']) ? (string) $first['name'] : $menu->name;
        } elseif (array_key_exists('name', $input)) {
            $update['name'] = $input['name'];
            $menu->translations()->updateOrCreate(
                ['locale' => $defaultLocale],
                ['name' => (string) $input['name'], 'description' => null]
            );
        }

        if ($update !== []) {
            $menu->update($update);
        }

        return $menu->fresh(['translations']);
    }
}
