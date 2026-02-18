<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class UpdateRestaurant
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * @param array{name?: string, tagline?: string|null, address?: string, latitude?: float|string|null, longitude?: float|string|null, phone?: string|null, email?: string|null, website?: string|null, social_links?: array|null} $input
     */
    public function handle(User $user, Restaurant $restaurant, array $input): Restaurant
    {
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $data = [];

        if (array_key_exists('name', $input)) {
            $data['name'] = $input['name'];
        }
        if (array_key_exists('tagline', $input)) {
            $data['tagline'] = $input['tagline'];
        }
        // Slug is immutable after create (subdomain URL).
        if (array_key_exists('address', $input)) {
            $data['address'] = $input['address'];
        }
        if (array_key_exists('latitude', $input)) {
            $data['latitude'] = $input['latitude'];
        }
        if (array_key_exists('longitude', $input)) {
            $data['longitude'] = $input['longitude'];
        }
        if (array_key_exists('phone', $input)) {
            $data['phone'] = $input['phone'];
        }
        if (array_key_exists('email', $input)) {
            $data['email'] = $input['email'];
        }
        if (array_key_exists('website', $input)) {
            $data['website'] = $input['website'];
        }
        if (array_key_exists('social_links', $input)) {
            $data['social_links'] = $input['social_links'];
        }
        if (array_key_exists('default_locale', $input)) {
            $locale = strtolower($input['default_locale']);
            if (! $restaurant->languages()->where('locale', $locale)->exists()) {
                throw ValidationException::withMessages([
                    'default_locale' => [__('Default locale must be one of the installed languages.')],
                ]);
            }
            $data['default_locale'] = $locale;
        }

        return $this->restaurantRepository->update($restaurant, $data);
    }
}
