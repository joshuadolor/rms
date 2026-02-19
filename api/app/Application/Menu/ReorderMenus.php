<?php

namespace App\Application\Menu;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\User;

final readonly class ReorderMenus
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array<int, string>  $uuids  Ordered list of menu uuids
     */
    public function handle(User $user, string $restaurantUuid, array $uuids): bool
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return false;
        }

        $menus = \App\Models\Menu::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');

        foreach ($uuids as $index => $uuid) {
            $menu = $menus->get($uuid);
            if ($menu !== null) {
                $menu->update(['sort_order' => $index]);
            }
        }

        return true;
    }
}
