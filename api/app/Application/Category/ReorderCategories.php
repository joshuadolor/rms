<?php

namespace App\Application\Category;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\User;

final readonly class ReorderCategories
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param  array<int, string>  $uuids  Ordered list of category uuids
     */
    public function handle(User $user, string $restaurantUuid, string $menuUuid, array $uuids): bool
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return false;
        }

        $menu = $restaurant->menus()->where('uuid', $menuUuid)->first();
        if ($menu === null) {
            return false;
        }

        $categories = \App\Models\Category::query()
            ->where('menu_id', $menu->id)
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');

        foreach ($uuids as $index => $uuid) {
            $category = $categories->get($uuid);
            if ($category !== null) {
                $category->update(['sort_order' => $index]);
            }
        }

        return true;
    }
}
