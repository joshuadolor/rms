<?php

namespace App\Application\Restaurant;

use App\Domain\BusinessRules\FeatureAvailability;
use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\RestaurantLanguage;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreateRestaurant
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository,
        private FeatureAvailability $featureAvailability
    ) {}

    /**
     * @param array{name: string, tagline?: string|null, slug?: string, address?: string, latitude?: float|string|null, longitude?: float|string|null, phone?: string|null, email?: string|null, website?: string|null, social_links?: array|null} $input
     */
    public function handle(User $user, array $input): Restaurant
    {
        if (! $this->featureAvailability->canCreateRestaurant($user)) {
            throw new \App\Exceptions\ForbiddenException($this->featureAvailability->cannotCreateRestaurantReason($user));
        }

        $slug = isset($input['slug']) && trim((string) $input['slug']) !== ''
            ? Str::slug($input['slug'])
            : Str::slug($input['name']);

        $slug = $this->ensureUniqueSlug($slug);

        $defaultLocale = $input['default_locale'] ?? 'en';
        $attributes = [
            'user_id' => $user->id,
            'name' => $input['name'],
            'tagline' => $input['tagline'] ?? null,
            'slug' => $slug,
            'address' => $input['address'] ?? null,
            'latitude' => $input['latitude'] ?? null,
            'longitude' => $input['longitude'] ?? null,
            'phone' => $input['phone'] ?? null,
            'email' => $input['email'] ?? null,
            'website' => $input['website'] ?? null,
            'social_links' => $input['social_links'] ?? null,
            'default_locale' => $defaultLocale,
        ];

        $restaurant = $this->restaurantRepository->create($attributes);
        $restaurant->languages()->create(['locale' => $defaultLocale]);

        return $restaurant;
    }

    private function ensureUniqueSlug(string $slug): string
    {
        $base = $slug;
        $suffix = 0;

        while ($this->restaurantRepository->findBySlug($slug) !== null) {
            $suffix++;
            $slug = $base . '-' . $suffix;
        }

        return $slug;
    }
}
