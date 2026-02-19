<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\User;

final readonly class GetUserMenuItem
{
    public function handle(User $user, string $itemUuid): ?MenuItem
    {
        $item = MenuItem::query()
            ->where('uuid', $itemUuid)
            ->with(['translations', 'category', 'restaurant', 'sourceMenuItem.translations'])
            ->first();

        if ($item === null) {
            return null;
        }

        if ($item->isStandalone()) {
            return $item->user_id === $user->id ? $item : null;
        }

        return $item->restaurant && $item->restaurant->isOwnedBy($user) ? $item : null;
    }
}
