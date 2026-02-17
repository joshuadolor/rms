<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\User;

final readonly class ListRestaurantLanguages
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @return array<string>
     */
    public function handle(User $user, string $restaurantUuid): array
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return [];
        }

        return $restaurant->languages()->orderBy('locale')->pluck('locale')->all();
    }
}
