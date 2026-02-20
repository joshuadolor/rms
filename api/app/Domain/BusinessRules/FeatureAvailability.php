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
        $max = $this->maxRestaurantsForUser($user);
        if ($max === null) {
            return true; // paid: unlimited
        }
        return $this->restaurantRepository->countForUser($user) < $max;
    }

    /**
     * Maximum number of restaurants the user may have. Null means unlimited.
     */
    public function maxRestaurantsForUser(User $user): ?int
    {
        if ($user->is_paid ?? false) {
            return null; // paid: unlimited
        }
        return 1; // free tier: one restaurant only
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

    /**
     * Whether the user can create/update/delete custom menu item tags.
     * Product decision: custom tags are disabled; only default tags exist.
     */
    public function canManageCustomMenuItemTags(User $user): bool
    {
        return false;
    }

    /**
     * Human-readable reason when create/update/delete tag is disabled (403).
     */
    public function cannotManageCustomMenuItemTagsReason(User $user): string
    {
        return __('Custom menu item tags are not available. Use the default tags.');
    }
}
