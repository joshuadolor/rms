<?php

namespace Tests\Unit\Application\MenuItem;

use App\Application\MenuItem\CatalogMenuItemValidator;
use App\Models\MenuItem;
use App\Models\MenuItemVariantSku;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('menu')]
class CatalogMenuItemValidatorTest extends TestCase
{
    // --- cartesianProduct ---

    public function test_cartesian_product_empty_groups_returns_empty(): void
    {
        $result = CatalogMenuItemValidator::cartesianProduct([]);
        $this->assertSame([], $result);
    }

    public function test_cartesian_product_single_group_returns_each_value(): void
    {
        $groups = [
            ['name' => 'Size', 'values' => ['S', 'M', 'L']],
        ];
        $result = CatalogMenuItemValidator::cartesianProduct($groups);
        $this->assertCount(3, $result);
        $this->assertEqualsCanonicalizing(
            [['Size' => 'S'], ['Size' => 'M'], ['Size' => 'L']],
            $result
        );
    }

    public function test_cartesian_product_two_groups_returns_all_combinations(): void
    {
        $groups = [
            ['name' => 'Type', 'values' => ['Hawaiian', 'Pepperoni']],
            ['name' => 'Size', 'values' => ['Small', 'Family']],
        ];
        $result = CatalogMenuItemValidator::cartesianProduct($groups);
        $this->assertCount(4, $result);
        $expected = [
            ['Type' => 'Hawaiian', 'Size' => 'Small'],
            ['Type' => 'Hawaiian', 'Size' => 'Family'],
            ['Type' => 'Pepperoni', 'Size' => 'Small'],
            ['Type' => 'Pepperoni', 'Size' => 'Family'],
        ];
        foreach ($expected as $e) {
            $this->assertContains($e, $result);
        }
    }

    // --- validateVariantSkusCoverCartesianProduct ---

    public function test_validate_variant_skus_accepts_full_cartesian_product(): void
    {
        $optionGroups = [
            ['name' => 'Type', 'values' => ['A', 'B']],
            ['name' => 'Size', 'values' => ['S', 'L']],
        ];
        $variantSkus = [
            ['option_values' => ['Type' => 'A', 'Size' => 'S'], 'price' => 8.0],
            ['option_values' => ['Type' => 'A', 'Size' => 'L'], 'price' => 12.0],
            ['option_values' => ['Type' => 'B', 'Size' => 'S'], 'price' => 9.0],
            ['option_values' => ['Type' => 'B', 'Size' => 'L'], 'price' => 14.0],
        ];
        CatalogMenuItemValidator::validateVariantSkusCoverCartesianProduct($optionGroups, $variantSkus);
        $this->expectNotToPerformAssertions();
    }

    public function test_validate_variant_skus_rejects_missing_combination(): void
    {
        $optionGroups = [
            ['name' => 'Type', 'values' => ['A', 'B']],
            ['name' => 'Size', 'values' => ['S', 'L']],
        ];
        $variantSkus = [
            ['option_values' => ['Type' => 'A', 'Size' => 'S'], 'price' => 8.0],
            ['option_values' => ['Type' => 'A', 'Size' => 'L'], 'price' => 12.0],
            ['option_values' => ['Type' => 'B', 'Size' => 'S'], 'price' => 9.0],
            // missing B + L
        ];
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Variant SKUs must cover exactly every combination');
        CatalogMenuItemValidator::validateVariantSkusCoverCartesianProduct($optionGroups, $variantSkus);
    }

    public function test_validate_variant_skus_rejects_duplicate_combination(): void
    {
        $optionGroups = [
            ['name' => 'Size', 'values' => ['S', 'L']],
        ];
        $variantSkus = [
            ['option_values' => ['Size' => 'S'], 'price' => 8.0],
            ['option_values' => ['Size' => 'L'], 'price' => 12.0],
            ['option_values' => ['Size' => 'S'], 'price' => 7.0],
        ];
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Duplicate variant combination');
        CatalogMenuItemValidator::validateVariantSkusCoverCartesianProduct($optionGroups, $variantSkus);
    }

    public function test_validate_variant_skus_rejects_missing_price(): void
    {
        $optionGroups = [
            ['name' => 'Size', 'values' => ['S']],
        ];
        $variantSkus = [
            ['option_values' => ['Size' => 'S']],
        ];
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Each variant must have a price');
        CatalogMenuItemValidator::validateVariantSkusCoverCartesianProduct($optionGroups, $variantSkus);
    }

    public function test_validate_variant_skus_rejects_extra_combination_not_in_cartesian(): void
    {
        $optionGroups = [
            ['name' => 'Size', 'values' => ['S']],
        ];
        $variantSkus = [
            ['option_values' => ['Size' => 'S'], 'price' => 8.0],
            ['option_values' => ['Size' => 'L'], 'price' => 12.0],
        ];
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Variant SKUs must cover exactly every combination');
        CatalogMenuItemValidator::validateVariantSkusCoverCartesianProduct($optionGroups, $variantSkus);
    }

    // --- validateComboEntries (requires DB) ---

    public function test_validate_combo_entries_accepts_owned_simple_items(): void
    {
        $user = User::factory()->create();
        $item = MenuItem::query()->create([
            'user_id' => $user->id,
            'restaurant_id' => null,
            'type' => MenuItem::TYPE_SIMPLE,
            'sort_order' => 0,
        ]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Burger', 'description' => null]);

        CatalogMenuItemValidator::validateComboEntries($user, [
            ['menu_item_uuid' => $item->uuid, 'quantity' => 1],
        ]);
        $this->expectNotToPerformAssertions();
    }

    public function test_validate_combo_entries_rejects_non_owned_item(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $item = MenuItem::query()->create([
            'user_id' => $owner->id,
            'restaurant_id' => null,
            'type' => MenuItem::TYPE_SIMPLE,
            'sort_order' => 0,
        ]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Burger', 'description' => null]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not exist or is not owned by you');
        CatalogMenuItemValidator::validateComboEntries($other, [
            ['menu_item_uuid' => $item->uuid, 'quantity' => 1],
        ]);
    }

    public function test_validate_combo_entries_rejects_variant_item_without_variant_uuid(): void
    {
        $user = User::factory()->create();
        $item = MenuItem::query()->create([
            'user_id' => $user->id,
            'restaurant_id' => null,
            'type' => MenuItem::TYPE_WITH_VARIANTS,
            'sort_order' => 0,
        ]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Pizza', 'description' => null]);
        $sku = MenuItemVariantSku::query()->create([
            'menu_item_id' => $item->id,
            'option_values' => ['Size' => 'Large'],
            'price' => 12.0,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A variant must be selected when the menu item has variants');
        CatalogMenuItemValidator::validateComboEntries($user, [
            ['menu_item_uuid' => $item->uuid, 'quantity' => 1],
        ]);
    }

    public function test_validate_combo_entries_accepts_variant_item_with_valid_variant_uuid(): void
    {
        $user = User::factory()->create();
        $item = MenuItem::query()->create([
            'user_id' => $user->id,
            'restaurant_id' => null,
            'type' => MenuItem::TYPE_WITH_VARIANTS,
            'sort_order' => 0,
        ]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Pizza', 'description' => null]);
        $sku = MenuItemVariantSku::query()->create([
            'menu_item_id' => $item->id,
            'option_values' => ['Size' => 'Large'],
            'price' => 12.0,
        ]);

        CatalogMenuItemValidator::validateComboEntries($user, [
            ['menu_item_uuid' => $item->uuid, 'variant_uuid' => $sku->uuid, 'quantity' => 1],
        ]);
        $this->expectNotToPerformAssertions();
    }

    public function test_validate_combo_entries_rejects_invalid_variant_uuid(): void
    {
        $user = User::factory()->create();
        $item = MenuItem::query()->create([
            'user_id' => $user->id,
            'restaurant_id' => null,
            'type' => MenuItem::TYPE_WITH_VARIANTS,
            'sort_order' => 0,
        ]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Pizza', 'description' => null]);
        MenuItemVariantSku::query()->create([
            'menu_item_id' => $item->id,
            'option_values' => ['Size' => 'Large'],
            'price' => 12.0,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The selected variant does not exist');
        CatalogMenuItemValidator::validateComboEntries($user, [
            ['menu_item_uuid' => $item->uuid, 'variant_uuid' => '00000000-0000-0000-0000-000000000001', 'quantity' => 1],
        ]);
    }

    public function test_validate_combo_entries_rejects_missing_menu_item_uuid(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Menu item UUID is required');
        CatalogMenuItemValidator::validateComboEntries($user, [
            ['quantity' => 1],
        ]);
    }
}
