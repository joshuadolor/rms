<?php

namespace App\Application\MenuItem;

use App\Models\ComboEntry;
use App\Models\MenuItem;
use App\Models\MenuItemVariantOptionGroup;
use App\Models\MenuItemVariantSku;
use App\Models\User;

final readonly class UpdateUserMenuItem
{
    public function __construct(
        private GetUserMenuItem $getUserMenuItem
    ) {}

    /**
     * @param  array{sort_order?: int, price?: float|null, type?: string, combo_price?: float|null, translations?: array, combo_entries?: array, variant_option_groups?: array, variant_skus?: array}  $input
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

        if ($item->isStandalone()) {
            $type = $input['type'] ?? $item->type;
            if (! in_array($type, [MenuItem::TYPE_SIMPLE, MenuItem::TYPE_COMBO, MenuItem::TYPE_WITH_VARIANTS], true)) {
                $type = $item->type ?? MenuItem::TYPE_SIMPLE;
            }
            $update['type'] = $type;
            if ($type === MenuItem::TYPE_SIMPLE) {
                $update['combo_price'] = null;
                if (array_key_exists('price', $input)) {
                    $update['price'] = $input['price'] === null ? null : (float) $input['price'];
                }
            }
            if ($type === MenuItem::TYPE_COMBO) {
                $update['price'] = null;
                $update['combo_price'] = array_key_exists('combo_price', $input) ? ($input['combo_price'] === null ? null : (float) $input['combo_price']) : $item->combo_price;
                $comboEntries = $input['combo_entries'] ?? null;
                if ($comboEntries !== null) {
                    if (count($comboEntries) < 1) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'combo_entries' => [__('A combo must have at least one entry.')],
                        ]);
                    }
                    CatalogMenuItemValidator::validateComboEntries($user, $comboEntries);
                }
            }
            if ($type === MenuItem::TYPE_WITH_VARIANTS) {
                $update['price'] = null;
                $update['combo_price'] = null;
                $optionGroups = $input['variant_option_groups'] ?? null;
                $variantSkus = $input['variant_skus'] ?? null;
                if ($optionGroups !== null && $variantSkus !== null) {
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
            }
        } elseif (array_key_exists('price', $input)) {
            $update['price'] = $input['price'] === null ? null : (float) $input['price'];
        }

        if ($update !== []) {
            $item->update($update);
        }

        $translations = $input['translations'] ?? [];
        $locales = $item->isStandalone()
            ? array_keys($translations)
            : ($item->restaurant ? $item->restaurant->languages()->pluck('locale')->all() : []);

        if ($item->isStandalone() && $locales === [] && $translations !== []) {
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

        // Sync type-specific data for standalone catalog items only
        if ($item->isStandalone()) {
            $item = $item->fresh();
            $type = $item->type ?? MenuItem::TYPE_SIMPLE;
            if ($type === MenuItem::TYPE_COMBO && array_key_exists('combo_entries', $input)) {
                $item->variantOptionGroups()->delete();
                $item->variantSkus()->delete();
                $item->comboEntries()->delete();
                $comboEntries = $input['combo_entries'];
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
            if ($type === MenuItem::TYPE_WITH_VARIANTS && array_key_exists('variant_option_groups', $input) && array_key_exists('variant_skus', $input)) {
                $item->comboEntries()->delete();
                $item->variantSkus()->delete();
                $item->variantOptionGroups()->delete();
                $optionGroups = $input['variant_option_groups'];
                foreach ($optionGroups as $sortOrderGroup => $g) {
                    MenuItemVariantOptionGroup::query()->create([
                        'menu_item_id' => $item->id,
                        'name' => $g['name'],
                        'values' => $g['values'],
                        'sort_order' => $sortOrderGroup,
                    ]);
                }
                $variantSkus = $input['variant_skus'];
                foreach ($variantSkus as $sku) {
                    MenuItemVariantSku::query()->create([
                        'menu_item_id' => $item->id,
                        'option_values' => $sku['option_values'],
                        'price' => (float) $sku['price'],
                        'image_url' => $sku['image_url'] ?? null,
                    ]);
                }
            }
            if ($type === MenuItem::TYPE_SIMPLE) {
                $item->comboEntries()->delete();
                $item->variantSkus()->delete();
                $item->variantOptionGroups()->delete();
            }
        }

        return $item->fresh([
            'translations',
            'category',
            'restaurant',
            'variantOptionGroups',
            'variantSkus',
            'comboEntries.referencedMenuItem.translations',
            'comboEntries.variant',
        ]);
    }
}
