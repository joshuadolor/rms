<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\MenuItemVariantSku;
use App\Models\User;
use App\Support\ImageExtensionFromMime;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class UploadMenuItemVariantImage
{
    private const SQUARE_SIZE = 512;

    public function handle(User $user, MenuItem $menuItem, MenuItemVariantSku $variantSku, UploadedFile $file): MenuItemVariantSku
    {
        $restaurant = $menuItem->restaurant;
        if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this restaurant.'));
        }

        if ((int) $variantSku->menu_item_id !== (int) $menuItem->id) {
            throw new \App\Exceptions\ForbiddenException(__('Variant does not belong to this menu item.'));
        }

        $disk = config('filesystems.default');
        $dir = 'restaurants/' . $restaurant->uuid . '/menu-items/' . $menuItem->uuid . '/variants/' . $variantSku->uuid;
        $ext = ImageExtensionFromMime::extension($file->getMimeType());
        $filename = 'image.' . $ext;

        $content = ImageResizer::resizeToSquare($file, self::SQUARE_SIZE);

        if ($variantSku->image_url && Storage::disk($disk)->exists($variantSku->image_url)) {
            Storage::disk($disk)->delete($variantSku->image_url);
        }

        $path = $dir . '/' . $filename;
        Storage::disk($disk)->put($path, $content);

        $variantSku->update(['image_url' => $path]);

        return $variantSku->fresh();
    }
}
