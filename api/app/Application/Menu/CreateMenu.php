<?php

namespace App\Application\Menu;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Menu;
use App\Models\User;

final readonly class CreateMenu
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array{name?: string|null, is_active?: bool, sort_order?: int, translations?: array<string, array{name: string, description?: string|null}>}  $input
     */
    public function handle(User $user, string $restaurantUuid, array $input): ?Menu
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null) {
            return null;
        }
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $sortOrder = array_key_exists('sort_order', $input)
            ? (int) $input['sort_order']
            : (int) $restaurant->menus()->max('sort_order') + 1;

        $defaultLocale = $restaurant->default_locale ?? 'en';
        $translationsInput = $input['translations'] ?? [];
        $legacyName = $input['name'] ?? null;

        if ($translationsInput === [] && $legacyName !== null) {
            $translationsInput = [$defaultLocale => ['name' => (string) $legacyName, 'description' => null]];
        }

        $resolvedName = null;
        if ($translationsInput !== []) {
            $first = $translationsInput[$defaultLocale] ?? reset($translationsInput);
            $resolvedName = isset($first['name']) ? (string) $first['name'] : null;
        }
        if ($resolvedName === null && $legacyName !== null) {
            $resolvedName = (string) $legacyName;
        }

        $menu = $restaurant->menus()->create([
            'name' => $resolvedName,
            'is_active' => $input['is_active'] ?? true,
            'sort_order' => $sortOrder,
        ]);

        $installedLocales = $restaurant->languages()->pluck('locale')->all();
        foreach ($translationsInput as $locale => $data) {
            if (! in_array($locale, $installedLocales, true)) {
                continue;
            }
            $name = isset($data['name']) ? (string) $data['name'] : ($resolvedName ?? '');
            $description = array_key_exists('description', $data) ? $data['description'] : null;
            $menu->translations()->create([
                'locale' => $locale,
                'name' => $name,
                'description' => $description !== null ? (string) $description : null,
            ]);
        }

        return $menu->load('translations');
    }
}
