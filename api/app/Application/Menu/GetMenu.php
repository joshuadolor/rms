<?php

namespace App\Application\Menu;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Menu;
use App\Models\User;

final readonly class GetMenu
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, string $restaurantUuid, string $menuUuid): ?Menu
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return null;
        }

        return Menu::query()
            ->where('uuid', $menuUuid)
            ->where('restaurant_id', $restaurant->id)
            ->first();
    }
}
