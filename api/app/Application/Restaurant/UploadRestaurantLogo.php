<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class UploadRestaurantLogo
{
    public function __construct(
        private RestaurantRepositoryInterface $restaurantRepository
    ) {}

    public function handle(User $user, Restaurant $restaurant, UploadedFile $file): Restaurant
    {
        if (! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $disk = config('filesystems.default');
        $dir = 'restaurants/' . $restaurant->uuid;
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = 'logo.' . strtolower($ext);

        if ($restaurant->logo_path && Storage::disk($disk)->exists($restaurant->logo_path)) {
            Storage::disk($disk)->delete($restaurant->logo_path);
        }

        $path = $file->storeAs($dir, $filename, $disk);

        $this->restaurantRepository->update($restaurant, ['logo_path' => $path]);

        return $restaurant->fresh();
    }
}
