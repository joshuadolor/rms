<?php

namespace App\Application\MenuItem;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class CatalogMenuItemValidator
{
    /**
     * Compute Cartesian product of option groups. Each group is [name => string, values => string[]].
     * Returns array of option_values maps, e.g. [['Type' => 'Pepperoni', 'Size' => 'Family'], ...].
     *
     * @param  array<int, array{name: string, values: array<string>}>  $groups
     * @return array<int, array<string, string>>
     */
    public static function cartesianProduct(array $groups): array
    {
        if ($groups === []) {
            return [];
        }
        $result = [[]];
        foreach ($groups as $group) {
            $name = $group['name'];
            $values = $group['values'] ?? [];
            $next = [];
            foreach ($result as $combo) {
                foreach ($values as $v) {
                    $next[] = $combo + [$name => $v];
                }
            }
            $result = $next;
        }
        return $result;
    }

    /**
     * Normalize option_values maps to comparable key-order and return sorted list of keys for comparison.
     *
     * @param  array<string, string>  $optionValues
     * @return string
     */
    public static function optionValuesKey(array $optionValues): string
    {
        ksort($optionValues);
        return json_encode($optionValues, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Validate that variant_skus cover exactly the Cartesian product. Each combination must appear once with a price.
     *
     * @param  array<int, array{name: string, values: array<string>}>  $optionGroups
     * @param  array<int, array{option_values: array<string, string>, price: float|int}>  $variantSkus
     * @throws ValidationException
     */
    public static function validateVariantSkusCoverCartesianProduct(array $optionGroups, array $variantSkus): void
    {
        $expected = self::cartesianProduct($optionGroups);
        $expectedKeys = collect($expected)->map(fn (array $ov) => self::optionValuesKey($ov))->sort()->values()->all();
        $seen = [];
        foreach ($variantSkus as $sku) {
            $ov = $sku['option_values'] ?? [];
            if (! array_key_exists('price', $sku)) {
                throw ValidationException::withMessages([
                    'variant_skus' => [__('Each variant must have a price.')],
                ]);
            }
            $key = self::optionValuesKey($ov);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'variant_skus' => [__('Duplicate variant combination.')],
                ]);
            }
            $seen[$key] = true;
        }
        $actualKeys = collect(array_keys($seen))->sort()->values()->all();
        if ($expectedKeys !== $actualKeys) {
            throw ValidationException::withMessages([
                'variant_skus' => [__('Variant SKUs must cover exactly every combination of option values (one per combination).')],
            ]);
        }
    }

    /**
     * Validate combo_entries: referenced items exist and are owned by user; when item has variants, variant_uuid required.
     *
     * @param  array<int, array{menu_item_uuid: string, variant_uuid?: string|null, quantity?: int, modifier_label?: string|null}>  $comboEntries
     * @throws ValidationException
     */
    public static function validateComboEntries(User $user, array $comboEntries): void
    {
        foreach ($comboEntries as $i => $entry) {
            $menuItemUuid = $entry['menu_item_uuid'] ?? null;
            if (! is_string($menuItemUuid) || $menuItemUuid === '') {
                throw ValidationException::withMessages([
                    "combo_entries.{$i}.menu_item_uuid" => [__('Menu item UUID is required.')],
                ]);
            }
            $ref = MenuItem::query()
                ->where('uuid', $menuItemUuid)
                ->where('user_id', $user->id)
                ->whereNull('restaurant_id')
                ->with('variantSkus')
                ->first();
            if ($ref === null) {
                throw ValidationException::withMessages([
                    "combo_entries.{$i}.menu_item_uuid" => [__('The selected menu item does not exist or is not owned by you.')],
                ]);
            }
            if ($ref->isWithVariants()) {
                $variantUuid = $entry['variant_uuid'] ?? null;
                if (! is_string($variantUuid) || $variantUuid === '') {
                    throw ValidationException::withMessages([
                        "combo_entries.{$i}.variant_uuid" => [__('A variant must be selected when the menu item has variants.')],
                    ]);
                }
                $variant = $ref->variantSkus()->where('uuid', $variantUuid)->first();
                if ($variant === null) {
                    throw ValidationException::withMessages([
                        "combo_entries.{$i}.variant_uuid" => [__('The selected variant does not exist for this menu item.')],
                    ]);
                }
            }
        }
    }
}
