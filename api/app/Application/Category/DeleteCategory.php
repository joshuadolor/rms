<?php

namespace App\Application\Category;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\User;

final readonly class DeleteCategory
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, string $restaurantUuid, string $menuUuid, string $categoryUuid): bool
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return false;
        }

        $menu = $restaurant->menus()->where('uuid', $menuUuid)->first();
        if ($menu === null) {
            return false;
        }

        $category = \App\Models\Category::query()
            ->where('uuid', $categoryUuid)
            ->where('menu_id', $menu->id)
            ->first();

        if ($category === null) {
            return false;
        }

        return $category->delete();
    }
}
