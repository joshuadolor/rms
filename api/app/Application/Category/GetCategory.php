<?php

namespace App\Application\Category;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Category;
use App\Models\User;

final readonly class GetCategory
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, string $restaurantUuid, string $menuUuid, string $categoryUuid): ?Category
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return null;
        }

        $menu = $restaurant->menus()->where('uuid', $menuUuid)->first();
        if ($menu === null) {
            return null;
        }

        return Category::query()
            ->where('uuid', $categoryUuid)
            ->where('menu_id', $menu->id)
            ->with('translations')
            ->first();
    }
}
