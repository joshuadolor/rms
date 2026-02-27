<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteMenuItemImage
{
    public function handle(User $user, MenuItem $menuItem): MenuItem
    {
        $restaurant = $menuItem->restaurant;
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        $disk = config('filesystems.default');
        if ($menuItem->image_path && Storage::disk($disk)->exists($menuItem->image_path)) {
            Storage::disk($disk)->delete($menuItem->image_path);
        }

        $menuItem->update(['image_path' => null]);

        return $menuItem->fresh();
    }
}
