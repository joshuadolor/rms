<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\User;

final readonly class UpdateUserMenuItem
{
    public function __construct(
        private GetUserMenuItem $getUserMenuItem
    ) {}

    /**
     * @param  array{sort_order?: int, translations?: array<string, array{name?: string, description?: string|null}>}  $input
     */
    public function handle(User $user, string $itemUuid, array $input): ?MenuItem
    {
        $item = $this->getUserMenuItem->handle($user, $itemUuid);
        if ($item === null) {
            return null;
        }

        $update = [];
        if (array_key_exists('sort_order', $input)) {
            $update['sort_order'] = (int) $input['sort_order'];
        }
        if ($item->isStandalone() && array_key_exists('price', $input)) {
            $update['price'] = $input['price'] === null ? null : (float) $input['price'];
        }
        if ($update !== []) {
            $item->update($update);
        }

        $translations = $input['translations'] ?? [];
        $locales = $item->isStandalone()
            ? array_keys($translations)
            : $item->restaurant->languages()->pluck('locale')->all();

        if ($item->isStandalone() && $locales === []) {
            $locales = array_keys($translations);
        }

        foreach ($locales as $locale) {
            $data = $translations[$locale] ?? [];
            $existing = $item->translations()->where('locale', $locale)->first();
            $name = array_key_exists('name', $data) ? (string) $data['name'] : ($existing?->name ?? '');
            $description = array_key_exists('description', $data) ? $data['description'] : ($existing?->description ?? null);
            $item->translations()->updateOrCreate(
                ['locale' => $locale],
                ['name' => $name, 'description' => $description]
            );
        }

        return $item->fresh(['translations', 'category', 'restaurant']);
    }
}
