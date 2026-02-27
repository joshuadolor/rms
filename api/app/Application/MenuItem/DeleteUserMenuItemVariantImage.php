<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\MenuItemVariantSku;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteUserMenuItemVariantImage
{
    public function handle(User $user, MenuItem $menuItem, MenuItemVariantSku $variantSku): MenuItemVariantSku
    {
        if (! $menuItem->isStandalone() || (int) $menuItem->user_id !== (int) $user->id) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this menu item.'));
        }

        if ((int) $variantSku->menu_item_id !== (int) $menuItem->id) {
            throw new \App\Exceptions\ForbiddenException(__('Variant does not belong to this menu item.'));
        }

        $disk = config('filesystems.default');
        if ($variantSku->image_url && Storage::disk($disk)->exists($variantSku->image_url)) {
            Storage::disk($disk)->delete($variantSku->image_url);
        }

        $variantSku->update(['image_url' => null]);

        return $variantSku->fresh();
    }
}
