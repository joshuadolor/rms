<?php

namespace App\Application\MenuItem;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\MenuItem;
use App\Models\User;

final readonly class GetMenuItem
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, string $restaurantUuid, string $itemUuid): ?MenuItem
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return null;
        }

        return MenuItem::query()
            ->where('uuid', $itemUuid)
            ->where('restaurant_id', $restaurant->id)
            ->with(['translations', 'category', 'sourceMenuItem.translations', 'sourceVariantSku', 'menuItemTags', 'variantSkus'])
            ->first();
    }
}
