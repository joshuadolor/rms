<?php

namespace App\Application\Menu;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class ListMenus
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @return Collection<int, Menu>|null
     */
    public function handle(User $user, string $restaurantUuid): ?Collection
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return null;
        }

        return $restaurant->menus()->with('translations')->orderBy('sort_order')->orderBy('id')->get();
    }
}
