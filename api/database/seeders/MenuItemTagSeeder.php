<?php

namespace Database\Seeders;

use App\Models\MenuItemTag;
use Illuminate\Database\Seeder;

class MenuItemTagSeeder extends Seeder
{
    /**
     * Default (system) tags available to all users. Each has color, icon, text.
     * user_id null = default tag.
     */
    public function run(): void
    {
        $defaults = [
            ['text' => 'Spicy', 'color' => '#dc2626', 'icon' => 'local_fire_department'],
            ['text' => 'Best Seller', 'color' => '#ea580c', 'icon' => 'star'],
            ['text' => 'Vegan', 'color' => '#16a34a', 'icon' => 'eco'],
            ['text' => 'Chicken', 'color' => '#ca8a04', 'icon' => 'egg'],
            ['text' => 'Gluten Free', 'color' => '#2563eb', 'icon' => 'check_circle'],
            ['text' => 'New', 'color' => '#7c3aed', 'icon' => 'fiber_new'],
            ['text' => "Chef's Choice", 'color' => '#be185d', 'icon' => 'restaurant'],
            ['text' => 'Popular', 'color' => '#0d9488', 'icon' => 'trending_up'],
            ['text' => 'Vegetarian', 'color' => '#15803d', 'icon' => 'park'],
        ];

        foreach ($defaults as $row) {
            $tag = MenuItemTag::query()->firstOrCreate(
                [
                    'text' => $row['text'],
                    'user_id' => null,
                ],
                [
                    'color' => $row['color'],
                    'icon' => $row['icon'],
                ]
            );
            // Update existing default tags so icon/color stay in sync (e.g. after fixing icon names)
            if ($tag->wasRecentlyCreated === false) {
                $tag->update(['color' => $row['color'], 'icon' => $row['icon']]);
            }
        }
    }
}
