<?php

namespace App\Application\MenuItemTag;

use App\Models\MenuItemTag;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class ListMenuItemTags
{
    /**
     * Returns default (system) tags only. Custom tags are no longer supported.
     *
     * @return Collection<int, MenuItemTag>
     */
    public function handle(User $user): Collection
    {
        return MenuItemTag::query()
            ->whereNull('user_id')
            ->orderBy('text')
            ->get();
    }
}
