<?php

namespace App\Application\Restaurant;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\ImageExtensionFromMime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class UploadRestaurantBanner
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
        $ext = ImageExtensionFromMime::extension($file->getMimeType());
        $filename = 'banner.' . $ext;

        if ($restaurant->banner_path && Storage::disk($disk)->exists($restaurant->banner_path)) {
            Storage::disk($disk)->delete($restaurant->banner_path);
        }

        $path = $file->storeAs($dir, $filename, $disk);

        $this->restaurantRepository->update($restaurant, ['banner_path' => $path]);

        return $restaurant->fresh();
    }
}
