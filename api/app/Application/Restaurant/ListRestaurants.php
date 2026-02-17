<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListRestaurants
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @return LengthAwarePaginator<\App\Models\Restaurant>
     */
    public function handle(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->restaurantRepository->paginateForUser($user->id, $perPage);
    }
}
