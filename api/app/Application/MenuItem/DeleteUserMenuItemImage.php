<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteUserMenuItemImage
{
    public function handle(User $user, MenuItem $menuItem): MenuItem
    {
        if (! $menuItem->isStandalone() || (int) $menuItem->user_id !== (int) $user->id) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this menu item.'));
        }

        $disk = config('filesystems.default');
        if ($menuItem->image_path && Storage::disk($disk)->exists($menuItem->image_path)) {
            Storage::disk($disk)->delete($menuItem->image_path);
        }

        $menuItem->update(['image_path' => null]);

        return $menuItem->fresh();
    }
}
