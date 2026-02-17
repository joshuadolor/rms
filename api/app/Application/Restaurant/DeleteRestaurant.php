<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteRestaurant
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, Restaurant $restaurant): void
    {
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $disk = config('filesystems.default');
        $prefix = 'restaurants/' . $restaurant->uuid;
        if ($restaurant->logo_path && Storage::disk($disk)->exists($restaurant->logo_path)) {
            Storage::disk($disk)->delete($restaurant->logo_path);
        }
        if ($restaurant->banner_path && Storage::disk($disk)->exists($restaurant->banner_path)) {
            Storage::disk($disk)->delete($restaurant->banner_path);
        }

        $this->restaurantRepository->delete($restaurant);
    }
}
