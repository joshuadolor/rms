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
     * @param  array{name?: string|null, is_active?: bool, sort_order?: int}  $input
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

        $menu = $restaurant->menus()->create([
            'name' => $input['name'] ?? null,
            'is_active' => $input['is_active'] ?? true,
            'sort_order' => $sortOrder,
        ]);

        return $menu;
    }
}
