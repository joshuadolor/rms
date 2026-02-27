<?php

namespace App\Application\Category;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteCategoryImage
{
    public function handle(User $user, Category $category): Category
    {
        $restaurant = $category->menu?->restaurant;
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $disk = config('filesystems.default');
        if ($category->image_path && Storage::disk($disk)->exists($category->image_path)) {
            Storage::disk($disk)->delete($category->image_path);
        }

        $category->update(['image_path' => null]);

        return $category->fresh();
    }
}
