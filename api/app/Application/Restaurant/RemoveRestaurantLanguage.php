<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class RemoveRestaurantLanguage
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, string $restaurantUuid, string $locale): bool
    {
        $restaurant = $this->restaurantRepository->findByUuid($restaurantUuid);
        if ($restaurant === null) {
            return false;
        }
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $locale = strtolower($locale);
        if ($restaurant->default_locale === $locale) {
            throw ValidationException::withMessages([
                'locale' => [__('Cannot remove the default language. Set another language as default first.')],
            ]);
        }

        $restaurant->languages()->where('locale', $locale)->delete();
        $restaurant->translations()->where('locale', $locale)->delete();

        return true;
    }
}
