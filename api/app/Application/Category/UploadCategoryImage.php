<?php

namespace App\Application\Category;

use App\Models\Category;
use App\Models\User;
use App\Support\ImageExtensionFromMime;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class UploadCategoryImage
{
    private const SQUARE_SIZE = 512;

    public function handle(User $user, Category $category, UploadedFile $file): Category
    {
        $restaurant = $category->menu?->restaurant;
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $disk = config('filesystems.default');
        $dir = 'restaurants/' . $restaurant->uuid . '/menus/' . $category->menu->uuid . '/categories/' . $category->uuid;
        $ext = ImageExtensionFromMime::extension($file->getMimeType());
        $filename = 'image.' . $ext;

        $content = ImageResizer::resizeToSquare($file, self::SQUARE_SIZE);

        if ($category->image_path && Storage::disk($disk)->exists($category->image_path)) {
            Storage::disk($disk)->delete($category->image_path);
        }

        $path = $dir . '/' . $filename;
        Storage::disk($disk)->put($path, $content);

        $category->update(['image_path' => $path]);

        return $category->fresh();
    }
}
