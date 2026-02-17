<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class UpsertRestaurantTranslation
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, string $restaurantUuid, string $locale, ?string $description): ?Restaurant
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null) {
            return null;
        }
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $locale = strtolower($locale);
        if (! $restaurant->languages()->where('locale', $locale)->exists()) {
            throw ValidationException::withMessages([
                'locale' => [__('This language is not installed for the restaurant.')],
            ]);
        }

        $restaurant->translations()->updateOrCreate(
            ['locale' => $locale],
            ['description' => $description]
        );

        return $restaurant->fresh();
    }
}
