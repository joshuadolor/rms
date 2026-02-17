<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\User;

final readonly class GetRestaurantTranslations
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @return array<string, array{description: string|null}>
     */
    public function handle(User $user, string $restaurantUuid): array
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            return [];
        }

        $translations = $restaurant->translations()->get();
        $result = [];
        foreach ($translations as $t) {
            $result[$t->locale] = ['description' => $t->description];
        }

        return $result;
    }
}
