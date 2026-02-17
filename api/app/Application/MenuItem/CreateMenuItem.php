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
     * @param  array{sort_order?: int, translations?: array<string, array{name: string, description?: string|null}>}  $input
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

        $sortOrder = (int) ($input['sort_order'] ?? $restaurant->menuItems()->max('sort_order') + 1);
        $item = $restaurant->menuItems()->create(['sort_order' => $sortOrder]);

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
