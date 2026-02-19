<?php

namespace App\Application\Category;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class ListCategories
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @return Collection<int, Category>|null
     */
    public function handle(User $user, string $restaurantUuid, string $menuUuid): ?Collection
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return null;
        }

        $menu = $restaurant->menus()->where('uuid', $menuUuid)->first();
        if ($menu === null) {
            return null;
        }

        return $menu->categories()->with('translations')->orderBy('sort_order')->orderBy('id')->get();
    }
}
