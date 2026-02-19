<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('menu')]
class MenuTest extends TestCase
{
    private function createVerifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function createRestaurantForUser(User $user): Restaurant
    {
        $r = new Restaurant;
        $r->user_id = $user->id;
        $r->name = 'Test Restaurant';
        $r->slug = 'test-restaurant-' . uniqid();
        $r->default_locale = 'en';
        $r->save();
        $r->languages()->create(['locale' => 'en']);

        return $r;
    }

    public function test_list_menus_returns_empty_for_new_restaurant(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_create_menu_succeeds_and_list_returns_it(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menus', [
            'name' => 'Lunch Menu',
            'is_active' => true,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Menu created.')
            ->assertJsonPath('data.name', 'Lunch Menu')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => ['uuid', 'name', 'is_active', 'sort_order', 'translations', 'created_at', 'updated_at']]);

        $menuUuid = $response->json('data.uuid');
        $list = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus', [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $list->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $menuUuid);
    }

    public function test_show_menu_returns_menu_payload(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Lunch', 'is_active' => true, 'sort_order' => 0]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.uuid', $menu->uuid)
            ->assertJsonPath('data.name', 'Lunch')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => ['uuid', 'name', 'is_active', 'sort_order', 'translations', 'created_at', 'updated_at']])
            ->assertJsonMissingPath('data.id');
    }

    public function test_delete_menu_succeeds(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'To Remove', 'sort_order' => 0]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_create_second_menu_succeeds_and_list_returns_both(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $first = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menus', [
            'name' => 'Lunch Menu',
            'is_active' => true,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $first->assertStatus(201)
            ->assertJsonPath('data.name', 'Lunch Menu');
        $firstMenuUuid = $first->json('data.uuid');

        $second = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menus', [
            'name' => 'Dinner Menu',
            'is_active' => false,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $second->assertStatus(201)
            ->assertJsonPath('data.name', 'Dinner Menu')
            ->assertJsonPath('data.is_active', false);
        $secondMenuUuid = $second->json('data.uuid');

        $this->assertNotSame($firstMenuUuid, $secondMenuUuid);

        $list = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus', [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $list->assertOk()
            ->assertJsonCount(2, 'data');

        $uuids = array_column($list->json('data'), 'uuid');
        $names = array_column($list->json('data'), 'name');
        $this->assertContains($firstMenuUuid, $uuids);
        $this->assertContains($secondMenuUuid, $uuids);
        $this->assertContains('Lunch Menu', $names);
        $this->assertContains('Dinner Menu', $names);
    }

    public function test_update_menu_toggles_is_active(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Dinner', 'is_active' => true, 'sort_order' => 0]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid, [
            'is_active' => false,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->assertFalse($menu->fresh()->is_active);
    }

    public function test_update_menu_updates_name(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Lunch', 'is_active' => true, 'sort_order' => 0]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid, [
            'name' => 'Brunch Menu',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Brunch Menu')
            ->assertJsonPath('data.uuid', $menu->uuid);
        $this->assertSame('Brunch Menu', $menu->fresh()->name);
    }

    public function test_reorder_menus_updates_sort_order(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $m1 = $restaurant->menus()->create(['name' => 'First', 'sort_order' => 0]);
        $m2 = $restaurant->menus()->create(['name' => 'Second', 'sort_order' => 1]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menus/reorder', [
            'order' => [$m2->uuid, $m1->uuid],
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Order updated.');
        $this->assertSame(0, $m2->fresh()->sort_order);
        $this->assertSame(1, $m1->fresh()->sort_order);
    }

    public function test_list_categories_returns_empty_for_new_menu(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_create_category_succeeds_with_translations(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories', [
            'translations' => [
                'en' => ['name' => 'Starters'],
            ],
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Category created.')
            ->assertJsonPath('data.translations.en.name', 'Starters')
            ->assertJsonStructure(['data' => ['uuid', 'sort_order', 'translations', 'created_at', 'updated_at']]);

        $catUuid = $response->json('data.uuid');
        $list = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories', [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $list->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $catUuid);
    }

    public function test_show_category_returns_category_payload(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0, 'is_active' => true]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Starters']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories/' . $category->uuid, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.uuid', $category->uuid)
            ->assertJsonPath('data.translations.en.name', 'Starters')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => ['uuid', 'sort_order', 'is_active', 'translations', 'created_at', 'updated_at']])
            ->assertJsonMissingPath('data.id');
    }

    public function test_update_category_toggles_is_active(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0, 'is_active' => true]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories/' . $category->uuid, [
            'is_active' => false,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Category updated.')
            ->assertJsonPath('data.is_active', false);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_delete_category_uncategorizes_menu_items(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'To Remove']);
        $item = $restaurant->menuItems()->create([
            'category_id' => $category->id,
            'sort_order' => 0,
        ]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Item A']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories/' . $category->uuid, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $item->refresh();
        $this->assertNull($item->category_id);
    }

    public function test_reorder_categories_updates_sort_order(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $c1 = $menu->categories()->create(['sort_order' => 0]);
        $c1->translations()->create(['locale' => 'en', 'name' => 'A']);
        $c2 = $menu->categories()->create(['sort_order' => 1]);
        $c2->translations()->create(['locale' => 'en', 'name' => 'B']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories/reorder', [
            'order' => [$c2->uuid, $c1->uuid],
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk();
        $this->assertSame(0, $c2->fresh()->sort_order);
        $this->assertSame(1, $c1->fresh()->sort_order);
    }

    public function test_menu_item_create_with_price_returns_price_in_payload(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'category_uuid' => $category->uuid,
            'price' => 12.99,
            'translations' => ['en' => ['name' => 'Burger', 'description' => 'Beef']],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
            ->assertJsonPath('data.price', 12.99)
            ->assertJsonPath('data.translations.en.name', 'Burger')
            ->assertJsonStructure(['data' => ['uuid', 'category_uuid', 'sort_order', 'price', 'translations', 'created_at', 'updated_at']])
            ->assertJsonMissingPath('data.id');
    }

    public function test_show_menu_item_returns_item_payload(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $category = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0])
            ->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $item = $restaurant->menuItems()->create(['category_id' => $category->id, 'sort_order' => 0, 'price' => 9.99]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Salad', 'description' => 'Fresh']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.uuid', $item->uuid)
            ->assertJsonPath('data.price', 9.99)
            ->assertJsonPath('data.translations.en.name', 'Salad')
            ->assertJsonMissingPath('data.id');
    }

    public function test_update_menu_item_succeeds(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $category = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0])
            ->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $item = $restaurant->menuItems()->create(['category_id' => $category->id, 'sort_order' => 0, 'price' => 10]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Old Name']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'price' => 11.50,
            'translations' => ['en' => ['name' => 'Updated Name', 'description' => 'New desc']],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertOk()
            ->assertJsonPath('message', 'Menu item updated.')
            ->assertJsonPath('data.price', 11.50)
            ->assertJsonPath('data.translations.en.name', 'Updated Name')
            ->assertJsonPath('data.translations.en.description', 'New desc');
        $item->refresh();
        $this->assertSame(11.50, (float) $item->price);
    }

    public function test_delete_menu_item_succeeds(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $category = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0])
            ->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $item = $restaurant->menuItems()->create(['category_id' => $category->id, 'sort_order' => 0]);
        $item->translations()->create(['locale' => 'en', 'name' => 'To Remove']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_menu_item_can_have_category_and_reorder_within_category(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $create1 = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'category_uuid' => $category->uuid,
            'translations' => ['en' => ['name' => 'Burger', 'description' => 'Beef']],
        ], ['Authorization' => 'Bearer ' . $token]);
        $create1->assertStatus(201);
        $item1Uuid = $create1->json('data.uuid');

        $create2 = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'category_uuid' => $category->uuid,
            'translations' => ['en' => ['name' => 'Pizza']],
        ], ['Authorization' => 'Bearer ' . $token]);
        $create2->assertStatus(201);
        $item2Uuid = $create2->json('data.uuid');

        $list = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $list->assertOk()
            ->assertJsonCount(2, 'data');
        $list->assertJsonPath('data.0.category_uuid', $category->uuid);

        $reorder = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/categories/' . $category->uuid . '/menu-items/reorder', [
            'order' => [$item2Uuid, $item1Uuid],
        ], ['Authorization' => 'Bearer ' . $token]);
        $reorder->assertOk()
            ->assertJsonPath('message', 'Order updated.');
    }

    public function test_create_restaurant_menu_item_from_catalog_with_category_uuid(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $restaurant->languages()->firstOrCreate(['locale' => 'en']);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $catalog = $this->postJson('/api/menu-items', [
            'price' => 8.50,
            'translations' => ['en' => ['name' => 'Catalog Burger', 'description' => 'From catalog']],
        ], ['Authorization' => 'Bearer ' . $token]);
        $catalog->assertStatus(201);
        $catalogUuid = $catalog->json('data.uuid');

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'source_menu_item_uuid' => $catalogUuid,
            'category_uuid' => $category->uuid,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
            ->assertJsonPath('data.source_menu_item_uuid', $catalogUuid)
            ->assertJsonPath('data.category_uuid', $category->uuid)
            ->assertJsonPath('data.translations.en.name', 'Catalog Burger')
            ->assertJsonPath('data.price', 8.50);
    }

    public function test_update_menu_item_category_uuid_moves_to_another_category(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $cat1 = $menu->categories()->create(['sort_order' => 0]);
        $cat1->translations()->create(['locale' => 'en', 'name' => 'Starters']);
        $cat2 = $menu->categories()->create(['sort_order' => 1]);
        $cat2->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $item = $restaurant->menuItems()->create(['category_id' => $cat1->id, 'sort_order' => 0, 'price' => 5.00]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Soup']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'category_uuid' => $cat2->uuid,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertOk()
            ->assertJsonPath('message', 'Menu item updated.')
            ->assertJsonPath('data.category_uuid', $cat2->uuid);
        $item->refresh();
        $this->assertSame($cat2->id, $item->category_id);
    }

    public function test_menus_require_ownership(): void
    {
        $owner = $this->createVerifiedUser();
        $other = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($owner);
        $token = $other->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404);
    }
}
