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
     * @param  array{name?: string|null, is_active?: bool, sort_order?: int}  $input
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
            ->first();

        if ($menu === null) {
            return null;
        }

        $update = [];
        if (array_key_exists('name', $input)) {
            $update['name'] = $input['name'];
        }
        if (array_key_exists('is_active', $input)) {
            $update['is_active'] = (bool) $input['is_active'];
        }
        if (array_key_exists('sort_order', $input)) {
            $update['sort_order'] = (int) $input['sort_order'];
        }
        if ($update !== []) {
            $menu->update($update);
        }

        return $menu->fresh();
    }
}
