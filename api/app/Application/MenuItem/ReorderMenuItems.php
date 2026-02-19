<?php

namespace App\Application\MenuItem;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\User;

final readonly class ReorderMenuItems
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * Reorder menu items within a category. Only items that belong to this category are updated.
     *
     * @param  array<int, string>  $itemUuids  Ordered list of menu item uuids
     */
    public function handle(User $user, string $restaurantUuid, string $categoryUuid, array $itemUuids): bool
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return false;
        }

        $category = \App\Models\Category::query()
            ->whereHas('menu', fn ($q) => $q->where('restaurant_id', $restaurant->id))
            ->where('uuid', $categoryUuid)
            ->first();

        if ($category === null) {
            return false;
        }

        $items = \App\Models\MenuItem::query()
            ->where('category_id', $category->id)
            ->whereIn('uuid', $itemUuids)
            ->get()
            ->keyBy('uuid');

        foreach ($itemUuids as $index => $uuid) {
            $item = $items->get($uuid);
            if ($item !== null) {
                $item->update(['sort_order' => $index]);
            }
        }

        return true;
    }
}
