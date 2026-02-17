<?php

namespace App\Application\MenuItem;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\MenuItem;
use App\Models\User;

final readonly class UpdateMenuItem
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array{sort_order?: int, translations?: array<string, array{name?: string, description?: string|null}>}  $input
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

        if (array_key_exists('sort_order', $input)) {
            $item->update(['sort_order' => (int) $input['sort_order']]);
        }

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

        return $item->fresh(['translations']);
    }
}
