<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreateStandaloneMenuItem
{
    /**
     * Create a menu item not tied to any restaurant (standalone). At least one translation with name is required.
     *
     * @param  array{sort_order?: int, translations: array<string, array{name: string, description?: string|null}>}  $input
     */
    public function handle(User $user, array $input): MenuItem
    {
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $price = isset($input['price']) ? (float) $input['price'] : null;
        $item = MenuItem::query()->create([
            'user_id' => $user->id,
            'restaurant_id' => null,
            'category_id' => null,
            'sort_order' => $sortOrder,
            'price' => $price,
        ]);

        $translations = $input['translations'] ?? [];
        $defaultLocale = 'en';
        $defaultData = $translations[$defaultLocale] ?? reset($translations) ?: [];

        $locales = array_keys($translations);
        if ($locales === []) {
            $locales = [$defaultLocale];
            $translations = [$defaultLocale => $defaultData];
        }

        foreach ($locales as $locale) {
            $data = $translations[$locale] ?? $defaultData;
            $name = isset($data['name']) ? (string) $data['name'] : '';
            $description = array_key_exists('description', $data) ? $data['description'] : null;
            $item->translations()->create([
                'locale' => $locale,
                'name' => $name,
                'description' => $description,
            ]);
        }

        return $item->load('translations');
    }
}
