<?php

namespace App\Application\MenuItem;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class ListMenuItems
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @return Collection<int, MenuItem>|null
     */
    public function handle(User $user, string $restaurantUuid): ?Collection
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return null;
        }

        return $restaurant->menuItems()
            ->with(['translations', 'category', 'sourceMenuItem.translations', 'sourceVariantSku', 'menuItemTags', 'variantSkus'])
            ->orderByRaw('category_id is null')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
