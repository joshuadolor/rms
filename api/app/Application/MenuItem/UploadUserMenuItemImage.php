<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\User;
use App\Support\ImageExtensionFromMime;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class UploadUserMenuItemImage
{
    private const SQUARE_SIZE = 512;

    public function handle(User $user, MenuItem $menuItem, UploadedFile $file): MenuItem
    {
        if (! $menuItem->isStandalone() || (int) $menuItem->user_id !== (int) $user->id) {
            throw new \App\Exceptions\ForbiddenException(__('You do not own this menu item.'));
        }

        $disk = config('filesystems.default');
        $dir = 'users/' . $user->id . '/menu-items/' . $menuItem->uuid;
        $ext = ImageExtensionFromMime::extension($file->getMimeType());
        $filename = 'image.' . $ext;

        $content = ImageResizer::resizeToSquare($file, self::SQUARE_SIZE);

        if ($menuItem->image_path && Storage::disk($disk)->exists($menuItem->image_path)) {
            Storage::disk($disk)->delete($menuItem->image_path);
        }

        $path = $dir . '/' . $filename;
        Storage::disk($disk)->put($path, $content);

        $menuItem->update(['image_path' => $path]);

        return $menuItem->fresh();
    }
}
