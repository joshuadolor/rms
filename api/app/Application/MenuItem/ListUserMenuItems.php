<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class ListUserMenuItems
{
    /**
     * List all menu items the user can access: standalone (user_id = user) and those in restaurants they own.
     *
     * @return Collection<int, MenuItem>
     */
    public function handle(User $user): Collection
    {
        return MenuItem::query()
            ->with(['translations', 'category', 'restaurant', 'sourceMenuItem.translations'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->whereNull('restaurant_id')
                    ->orWhereHas('restaurant', fn ($q) => $q->where('user_id', $user->id));
            })
            ->orderByRaw('restaurant_id is null DESC')
            ->orderByRaw('category_id is null')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
