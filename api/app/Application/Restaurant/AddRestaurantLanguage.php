<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class AddRestaurantLanguage
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, string $restaurantUuid, string $locale): ?Restaurant
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null) {
            return null;
        }
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $locale = strtolower($locale);
        $supported = config('locales.supported', ['en', 'nl', 'ru']);
        if (! in_array($locale, $supported, true)) {
            throw ValidationException::withMessages([
                'locale' => [__('Locale is not supported.')],
            ]);
        }

        if ($restaurant->languages()->where('locale', $locale)->exists()) {
            throw ValidationException::withMessages([
                'locale' => [__('This language is already installed.')],
            ]);
        }

        $restaurant->languages()->create(['locale' => $locale]);

        return $restaurant->fresh(['languages']);
    }
}
