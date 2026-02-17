<?php

namespace App\Domain\BusinessRules;

use App\Models\User;
use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;

/**
 * Central place for conditions that determine whether a feature is available to a user.
 * Used to support free vs paid tiers (e.g. free = one restaurant only).
 */
final readonly class FeatureAvailability
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * Whether the user is allowed to create another restaurant.
     */
    public function canCreateRestaurant(User $user): bool
    {
        return $this->restaurantRepository->countForUser($user) < $this->maxRestaurantsForUser($user);
    }

    /**
     * Maximum number of restaurants the user may have. Null means unlimited.
     */
    public function maxRestaurantsForUser(User $user): ?int
    {
        // Future: read from plan/subscription (e.g. $user->plan === 'paid' => null, 'free' => 1).
        return 1;
    }

    /**
     * Human-readable reason when canCreateRestaurant is false (e.g. for 403 message).
     */
    public function cannotCreateRestaurantReason(User $user): string
    {
        $max = $this->maxRestaurantsForUser($user);

        return $max === 1
            ? __('Free tier allows one restaurant. Upgrade to add more.')
            : __('Restaurant limit reached. Upgrade to add more.');
    }
}
