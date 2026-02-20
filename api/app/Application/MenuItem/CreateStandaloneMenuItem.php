<?php

namespace App\Application\MenuItem;

use App\Models\ComboEntry;
use App\Models\MenuItem;
use App\Models\MenuItemVariantOptionGroup;
use App\Models\MenuItemVariantSku;
use App\Models\User;

final readonly class CreateStandaloneMenuItem
{
    /**
     * Create a menu item not tied to any restaurant (standalone). At least one translation with name is required.
     *
     * @param  array{sort_order?: int, price?: float|null, type?: string, combo_price?: float|null, translations: array, combo_entries?: array, variant_option_groups?: array, variant_skus?: array}  $input
     */
    public function handle(User $user, array $input): MenuItem
    {
        $type = $input['type'] ?? MenuItem::TYPE_SIMPLE;
        if (! in_array($type, [MenuItem::TYPE_SIMPLE, MenuItem::TYPE_COMBO, MenuItem::TYPE_WITH_VARIANTS], true)) {
            $type = MenuItem::TYPE_SIMPLE;
        }

        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $price = null;
        $comboPrice = null;
        if ($type === MenuItem::TYPE_SIMPLE) {
            $price = isset($input['price']) ? (float) $input['price'] : null;
        }
        if ($type === MenuItem::TYPE_COMBO) {
            $comboPrice = isset($input['combo_price']) ? (float) $input['combo_price'] : null;
            $comboEntries = $input['combo_entries'] ?? [];
            if (count($comboEntries) < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'combo_entries' => [__('A combo must have at least one entry.')],
                ]);
            }
            CatalogMenuItemValidator::validateComboEntries($user, $comboEntries);
        }
        if ($type === MenuItem::TYPE_WITH_VARIANTS) {
            $optionGroups = $input['variant_option_groups'] ?? [];
            $variantSkus = $input['variant_skus'] ?? [];
            if (count($optionGroups) < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'variant_option_groups' => [__('At least one option group is required.')],
                ]);
            }
            foreach ($optionGroups as $i => $g) {
                $name = $g['name'] ?? '';
                $values = $g['values'] ?? [];
                if ($name === '' || ! is_array($values) || count($values) === 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "variant_option_groups.{$i}.name" => [__('Each option group must have a name and non-empty values.')],
                    ]);
                }
            }
            CatalogMenuItemValidator::validateVariantSkusCoverCartesianProduct($optionGroups, $variantSkus);
        }

        $item = MenuItem::query()->create([
            'user_id' => $user->id,
            'restaurant_id' => null,
            'category_id' => null,
            'sort_order' => $sortOrder,
            'price' => $price,
            'type' => $type,
            'combo_price' => $comboPrice,
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

        if ($type === MenuItem::TYPE_WITH_VARIANTS) {
            $optionGroups = $input['variant_option_groups'] ?? [];
            foreach ($optionGroups as $sortOrderGroup => $g) {
                MenuItemVariantOptionGroup::query()->create([
                    'menu_item_id' => $item->id,
                    'name' => $g['name'],
                    'values' => $g['values'],
                    'sort_order' => $sortOrderGroup,
                ]);
            }
            $variantSkus = $input['variant_skus'] ?? [];
            foreach ($variantSkus as $sku) {
                MenuItemVariantSku::query()->create([
                    'menu_item_id' => $item->id,
                    'option_values' => $sku['option_values'],
                    'price' => (float) $sku['price'],
                    'image_url' => $sku['image_url'] ?? null,
                ]);
            }
        }

        if ($type === MenuItem::TYPE_COMBO) {
            $comboEntries = $input['combo_entries'] ?? [];
            foreach ($comboEntries as $sortOrderEntry => $entry) {
                $ref = MenuItem::query()->where('uuid', $entry['menu_item_uuid'])->where('user_id', $user->id)->first();
                if ($ref === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "combo_entries.{$sortOrderEntry}.menu_item_uuid" => [__('The selected menu item does not exist or is not owned by you.')],
                    ]);
                }
                $variantId = null;
                if ($ref->isWithVariants() && ! empty($entry['variant_uuid'] ?? null)) {
                    $variant = MenuItemVariantSku::query()->where('menu_item_id', $ref->id)->where('uuid', $entry['variant_uuid'])->first();
                    $variantId = $variant?->id;
                }
                ComboEntry::query()->create([
                    'combo_menu_item_id' => $item->id,
                    'referenced_menu_item_id' => $ref->id,
                    'variant_id' => $variantId,
                    'quantity' => (int) ($entry['quantity'] ?? 1),
                    'modifier_label' => $entry['modifier_label'] ?? null,
                    'sort_order' => $sortOrderEntry,
                ]);
            }
        }

        return $item->load([
            'translations',
            'variantOptionGroups',
            'variantSkus',
            'comboEntries.referencedMenuItem.translations',
            'comboEntries.variant',
        ]);
    }
}
