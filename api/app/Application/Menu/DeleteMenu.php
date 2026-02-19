<?php

namespace App\Application\Menu;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\User;

final readonly class DeleteMenu
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, string $restaurantUuid, string $menuUuid): bool
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return false;
        }

        $menu = \App\Models\Menu::query()
            ->where('uuid', $menuUuid)
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if ($menu === null) {
            return false;
        }

        return $menu->delete();
    }
}
