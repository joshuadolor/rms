<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\User;

final readonly class GetRestaurant
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, string $uuid): ?Restaurant
    {
        $restaurant = $this->restaurantRepository->findByUuid($uuid);

        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return null;
        }

        return $restaurant;
    }
}
