<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class ListUserMenuItems
{
    /**
     * List only standalone (catalog) menu items for the user. Used by the "Menu items" catalog page.
     * Restaurant menu items (references to catalog or restaurant-only items) are not included;
     * they are listed per restaurant via the restaurant menu-items API.
     *
     * @return Collection<int, MenuItem>
     */
    public function handle(User $user): Collection
    {
        return MenuItem::query()
            ->with(['translations', 'category', 'sourceMenuItem.translations'])
            ->where('user_id', $user->id)
            ->whereNull('restaurant_id')
            ->orderByRaw('category_id is null')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
