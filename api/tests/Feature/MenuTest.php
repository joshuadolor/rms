<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuItemTag;
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
            ->assertJsonStructure(['data' => ['uuid', 'sort_order', 'is_active', 'availability', 'translations', 'created_at', 'updated_at']])
            ->assertJsonMissingPath('data.id');
    }

    public function test_list_categories_includes_availability_and_excludes_internal_id(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $availability = [
            'friday' => ['open' => true, 'slots' => [['from' => '18:00', 'to' => '23:00']]],
        ];
        $category = $menu->categories()->create(['sort_order' => 0, 'is_active' => true, 'availability' => $availability]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Weekend']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $category->uuid)
            ->assertJsonPath('data.0.availability.friday.open', true)
            ->assertJsonPath('data.0.availability.friday.slots.0.from', '18:00')
            ->assertJsonMissingPath('data.0.id');
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

    public function test_create_category_with_availability_persists_and_returns_in_payload(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $token = $user->createToken('auth')->plainTextToken;

        $availability = [
            'monday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '12:00'], ['from' => '14:00', 'to' => '17:00']]],
            'tuesday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '17:00']]],
            'wednesday' => ['open' => false, 'slots' => []],
        ];

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories', [
            'translations' => ['en' => ['name' => 'Lunch Only']],
            'availability' => $availability,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability.monday.open', true)
            ->assertJsonPath('data.availability.monday.slots.0.from', '09:00')
            ->assertJsonPath('data.availability.monday.slots.0.to', '12:00')
            ->assertJsonPath('data.availability.wednesday.open', false);
        $category = \App\Models\Category::where('uuid', $response->json('data.uuid'))->first();
        $this->assertSame($availability, $category->availability);
    }

    public function test_update_category_availability_and_null_clears(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0, 'is_active' => true, 'availability' => ['monday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '17:00']]]]]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories/' . $category->uuid, [
            'availability' => null,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.availability', null);
        $this->assertNull($category->fresh()->availability);
    }

    public function test_category_availability_rejects_overlapping_slots(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories', [
            'translations' => ['en' => ['name' => 'Bad Slots']],
            'availability' => [
                'monday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '12:00'], ['from' => '11:00', 'to' => '14:00']]],
            ],
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability']);
    }

    public function test_show_category_includes_availability_in_payload(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $availability = ['saturday' => ['open' => true, 'slots' => [['from' => '10:00', 'to' => '22:00']]]];
        $category = $menu->categories()->create(['sort_order' => 0, 'is_active' => true, 'availability' => $availability]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Brunch']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menus/' . $menu->uuid . '/categories/' . $category->uuid, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.uuid', $category->uuid)
            ->assertJsonPath('data.availability.saturday.open', true)
            ->assertJsonPath('data.availability.saturday.slots.0.from', '10:00')
            ->assertJsonMissingPath('data.id');
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
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => ['uuid', 'category_uuid', 'sort_order', 'is_active', 'is_available', 'price', 'translations', 'created_at', 'updated_at']])
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
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => ['uuid', 'category_uuid', 'sort_order', 'is_active', 'is_available', 'availability', 'price', 'translations', 'created_at', 'updated_at']])
            ->assertJsonMissingPath('data.id');
    }

    public function test_list_menu_items_includes_availability_and_excludes_internal_id(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $availability = ['sunday' => ['open' => true, 'slots' => [['from' => '11:00', 'to' => '21:00']]]];
        $item = $restaurant->menuItems()->create([
            'category_id' => $category->id,
            'sort_order' => 0,
            'price' => 12,
            'availability' => $availability,
        ]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Sunday Roast']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $item->uuid)
            ->assertJsonPath('data.0.availability.sunday.open', true)
            ->assertJsonPath('data.0.availability.sunday.slots.0.from', '11:00')
            ->assertJsonMissingPath('data.0.id');
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

    public function test_update_menu_item_toggles_is_active(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $category = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0])
            ->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $item = $restaurant->menuItems()->create(['category_id' => $category->id, 'sort_order' => 0, 'price' => 10, 'is_active' => true]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Burger']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'is_active' => false,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertOk()
            ->assertJsonPath('message', 'Menu item updated.')
            ->assertJsonPath('data.is_active', false);
        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_update_menu_item_toggles_is_available(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $category = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0])
            ->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $item = $restaurant->menuItems()->create(['category_id' => $category->id, 'sort_order' => 0, 'price' => 10, 'is_available' => true]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Burger']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'is_available' => false,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertOk()
            ->assertJsonPath('message', 'Menu item updated.')
            ->assertJsonPath('data.is_available', false);
        $this->assertFalse($item->fresh()->is_available);

        $response2 = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'is_available' => true,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response2->assertOk()->assertJsonPath('data.is_available', true);
    }

    public function test_menu_item_create_with_availability_persists_and_returns_in_payload(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $availability = [
            'friday' => ['open' => true, 'slots' => [['from' => '18:00', 'to' => '23:00']]],
            'saturday' => ['open' => true, 'slots' => [['from' => '12:00', 'to' => '23:00']]],
        ];

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'category_uuid' => $category->uuid,
            'translations' => ['en' => ['name' => 'Weekend Special']],
            'availability' => $availability,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability.friday.open', true)
            ->assertJsonPath('data.availability.friday.slots.0.from', '18:00')
            ->assertJsonPath('data.availability.saturday.slots.0.to', '23:00');
        $item = MenuItem::where('uuid', $response->json('data.uuid'))->where('restaurant_id', $restaurant->id)->first();
        $this->assertSame($availability, $item->availability);
    }

    public function test_update_menu_item_availability_and_null_clears(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $item = $restaurant->menuItems()->create(['category_id' => $category->id, 'sort_order' => 0, 'availability' => ['monday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '17:00']]]]]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Item']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'availability' => null,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.availability', null);
        $this->assertNull($item->fresh()->availability);
    }

    public function test_menu_item_availability_rejects_overlapping_slots(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'category_uuid' => $category->uuid,
            'translations' => ['en' => ['name' => 'Bad Slots Item']],
            'availability' => [
                'monday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '12:00'], ['from' => '11:00', 'to' => '14:00']]],
            ],
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability']);
    }

    public function test_menu_item_create_with_tag_uuids_creates_item_with_tags_and_response_includes_tags(): void
    {
        $defaultTag = MenuItemTag::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'text' => 'Spicy',
            'color' => '#dc2626',
            'icon' => 'local_fire_department',
            'user_id' => null,
        ]);
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'category_uuid' => $category->uuid,
            'translations' => ['en' => ['name' => 'Hot Wings', 'description' => 'Spicy']],
            'tag_uuids' => [$defaultTag->uuid],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
            ->assertJsonPath('data.translations.en.name', 'Hot Wings')
            ->assertJsonStructure(['data' => ['tags']])
            ->assertJsonPath('data.tags.0.uuid', (string) $defaultTag->uuid)
            ->assertJsonPath('data.tags.0.color', $defaultTag->color)
            ->assertJsonPath('data.tags.0.icon', $defaultTag->icon)
            ->assertJsonPath('data.tags.0.text', $defaultTag->text);
        $item = MenuItem::query()->where('uuid', $response->json('data.uuid'))->first();
        $this->assertNotNull($item);
        $this->assertSame(1, $item->menuItemTags()->count());
        $this->assertTrue($item->menuItemTags()->where('uuid', $defaultTag->uuid)->exists());
    }

    public function test_menu_item_update_with_tag_uuids_replaces_tags_and_response_includes_tags(): void
    {
        $tag1 = MenuItemTag::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'text' => 'Spicy',
            'color' => '#dc2626',
            'icon' => 'fire',
            'user_id' => null,
        ]);
        $tag2 = MenuItemTag::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'text' => 'Vegan',
            'color' => '#16a34a',
            'icon' => 'eco',
            'user_id' => null,
        ]);
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $category = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0])
            ->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $item = $restaurant->menuItems()->create(['category_id' => $category->id, 'sort_order' => 0]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Burger']);
        $item->menuItemTags()->attach($tag1->id);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'tag_uuids' => [$tag2->uuid],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertOk()
            ->assertJsonPath('message', 'Menu item updated.')
            ->assertJsonCount(1, 'data.tags')
            ->assertJsonPath('data.tags.0.uuid', (string) $tag2->uuid)
            ->assertJsonPath('data.tags.0.text', $tag2->text);
        $item->refresh();
        $this->assertSame(1, $item->menuItemTags()->count());
        $this->assertTrue($item->menuItemTags()->where('uuid', $tag2->uuid)->exists());
        $this->assertFalse($item->menuItemTags()->where('uuid', $tag1->uuid)->exists());
    }

    public function test_menu_item_create_with_invalid_tag_uuids_returns_422(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;
        $nonexistentUuid = '00000000-0000-0000-0000-000000000001';

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'category_uuid' => $category->uuid,
            'translations' => ['en' => ['name' => 'Item', 'description' => null]],
            'tag_uuids' => [$nonexistentUuid],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_uuids']);
    }

    public function test_menu_item_create_with_forbidden_tag_uuids_returns_422(): void
    {
        $otherUser = $this->createVerifiedUser();
        $forbiddenTag = MenuItemTag::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'text' => 'Other User Tag',
            'color' => '#000',
            'icon' => 'star',
            'user_id' => $otherUser->id,
        ]);
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'category_uuid' => $category->uuid,
            'translations' => ['en' => ['name' => 'Item', 'description' => null]],
            'tag_uuids' => [$forbiddenTag->uuid],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_uuids']);
    }

    public function test_menu_item_update_with_invalid_tag_uuids_returns_422(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $category = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0])
            ->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $item = $restaurant->menuItems()->create(['category_id' => $category->id, 'sort_order' => 0]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Burger']);
        $token = $user->createToken('auth')->plainTextToken;
        $nonexistentUuid = '00000000-0000-0000-0000-000000000002';

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'tag_uuids' => [$nonexistentUuid],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_uuids']);
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

    public function test_menu_item_image_upload_serve_and_delete(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $item = $restaurant->menuItems()->create(['sort_order' => 0, 'is_active' => true, 'is_available' => true]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Dish', 'description' => null]);
        $token = $user->createToken('auth')->plainTextToken;

        $showBefore = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $showBefore->assertOk()->assertJsonPath('data.image_url', null);

        $file = \Illuminate\Http\UploadedFile::fake()->image('item.jpg', 200, 200);
        $upload = $this->post('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid . '/image', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
        $upload->assertOk()
            ->assertJsonPath('message', 'Image updated.')
            ->assertJsonStructure(['data' => ['uuid', 'image_url']]);
        $imageUrl = $upload->json('data.image_url');
        $this->assertNotEmpty($imageUrl);
        $this->assertStringContainsString('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid . '/image', $imageUrl);

        $servePath = '/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid . '/image';
        $serve = $this->get($servePath);
        $serve->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $delete = $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/menu-items/' . $item->uuid . '/image', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $delete->assertOk()
            ->assertJsonPath('message', 'Image removed.')
            ->assertJsonPath('data.image_url', null);
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
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.category_uuid', $category->uuid)
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.1.is_active', true);

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

    public function test_create_restaurant_menu_item_from_catalog_with_variants_requires_source_variant_uuid(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $restaurant->languages()->firstOrCreate(['locale' => 'en']);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $catalog = $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Pizza', 'description' => null]],
            'variant_option_groups' => [['name' => 'Size', 'values' => ['S', 'M']]],
            'variant_skus' => [
                ['option_values' => ['Size' => 'S'], 'price' => 8.00],
                ['option_values' => ['Size' => 'M'], 'price' => 12.00],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);
        $catalog->assertStatus(201);
        $catalogUuid = $catalog->json('data.uuid');

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'source_menu_item_uuid' => $catalogUuid,
            'category_uuid' => $category->uuid,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source_variant_uuid']);
    }

    public function test_create_restaurant_menu_item_from_catalog_with_variants_accepts_source_variant_uuid(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $restaurant->languages()->firstOrCreate(['locale' => 'en']);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $catalog = $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Pizza', 'description' => null]],
            'variant_option_groups' => [['name' => 'Type', 'values' => ['Hawaiian', 'Pepperoni']], ['name' => 'Size', 'values' => ['Small', 'Family']]],
            'variant_skus' => [
                ['option_values' => ['Type' => 'Hawaiian', 'Size' => 'Small'], 'price' => 8.00],
                ['option_values' => ['Type' => 'Hawaiian', 'Size' => 'Family'], 'price' => 14.00],
                ['option_values' => ['Type' => 'Pepperoni', 'Size' => 'Small'], 'price' => 9.00],
                ['option_values' => ['Type' => 'Pepperoni', 'Size' => 'Family'], 'price' => 16.00],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);
        $catalog->assertStatus(201);
        $catalogUuid = $catalog->json('data.uuid');
        $variantUuids = array_column($catalog->json('data.variant_skus'), 'uuid');
        $hawaiianSmallUuid = $catalog->json('data.variant_skus.0.uuid');

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'source_menu_item_uuid' => $catalogUuid,
            'source_variant_uuid' => $hawaiianSmallUuid,
            'category_uuid' => $category->uuid,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
            ->assertJsonPath('data.source_menu_item_uuid', $catalogUuid)
            ->assertJsonPath('data.source_variant_uuid', $hawaiianSmallUuid)
            ->assertJsonPath('data.category_uuid', $category->uuid)
            ->assertJsonPath('data.translations.en.name', 'Pizza - Hawaiian, Small');
        $this->assertSame(8.0, (float) $response->json('data.price'));
        $this->assertSame(8.0, (float) $response->json('data.base_price'));
        $this->assertContains($response->json('data.source_variant_uuid'), $variantUuids);
    }

    public function test_create_restaurant_menu_item_from_catalog_simple_rejects_source_variant_uuid(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $restaurant->languages()->firstOrCreate(['locale' => 'en']);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains']);
        $token = $user->createToken('auth')->plainTextToken;

        $catalog = $this->postJson('/api/menu-items', [
            'price' => 6.00,
            'translations' => ['en' => ['name' => 'Burger', 'description' => null]],
        ], ['Authorization' => 'Bearer ' . $token]);
        $catalog->assertStatus(201);
        $catalogUuid = $catalog->json('data.uuid');

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/menu-items', [
            'source_menu_item_uuid' => $catalogUuid,
            'source_variant_uuid' => '00000000-0000-0000-0000-000000000001',
            'category_uuid' => $category->uuid,
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source_variant_uuid']);
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

    public function test_catalog_menu_item_with_variants_creates_option_groups_and_skus(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Pizza', 'description' => null]],
            'variant_option_groups' => [
                ['name' => 'Type', 'values' => ['Hawaiian', 'Pepperoni']],
                ['name' => 'Size', 'values' => ['Small', 'Family']],
            ],
            'variant_skus' => [
                ['option_values' => ['Type' => 'Hawaiian', 'Size' => 'Small'], 'price' => 8.00],
                ['option_values' => ['Type' => 'Hawaiian', 'Size' => 'Family'], 'price' => 14.00],
                ['option_values' => ['Type' => 'Pepperoni', 'Size' => 'Small'], 'price' => 9.00],
                ['option_values' => ['Type' => 'Pepperoni', 'Size' => 'Family'], 'price' => 16.00],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'with_variants')
            ->assertJsonPath('data.price', null)
            ->assertJsonPath('data.translations.en.name', 'Pizza');
        $data = $response->json('data');
        $this->assertCount(2, $data['variant_option_groups']);
        $this->assertCount(4, $data['variant_skus']);
        foreach ($data['variant_skus'] as $sku) {
            $this->assertArrayHasKey('uuid', $sku);
            $this->assertArrayHasKey('option_values', $sku);
            $this->assertArrayHasKey('price', $sku);
            $this->assertArrayNotHasKey('id', $sku);
        }
    }

    public function test_catalog_menu_item_combo_creates_entries_referencing_owned_items(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $burger = $this->postJson('/api/menu-items', [
            'translations' => ['en' => ['name' => 'Burger', 'description' => null]],
            'price' => 6.00,
        ], ['Authorization' => 'Bearer ' . $token]);
        $burger->assertStatus(201);
        $burgerUuid = $burger->json('data.uuid');

        $drink = $this->postJson('/api/menu-items', [
            'translations' => ['en' => ['name' => 'Drink', 'description' => null]],
            'price' => 2.00,
        ], ['Authorization' => 'Bearer ' . $token]);
        $drink->assertStatus(201);
        $drinkUuid = $drink->json('data.uuid');

        $response = $this->postJson('/api/menu-items', [
            'type' => 'combo',
            'combo_price' => 7.50,
            'translations' => ['en' => ['name' => 'Combo 1', 'description' => 'Burger + Drink']],
            'combo_entries' => [
                ['menu_item_uuid' => $burgerUuid, 'quantity' => 1],
                ['menu_item_uuid' => $drinkUuid, 'quantity' => 1, 'modifier_label' => 'Small'],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'combo')
            ->assertJsonPath('data.price', 7.5)
            ->assertJsonPath('data.combo_price', 7.5)
            ->assertJsonPath('data.translations.en.name', 'Combo 1');
        $data = $response->json('data');
        $this->assertCount(2, $data['combo_entries']);
        $entryUuids = array_column($data['combo_entries'], 'menu_item_uuid');
        $this->assertContains($burgerUuid, $entryUuids);
        $this->assertContains($drinkUuid, $entryUuids);
    }

    public function test_create_catalog_simple_item_explicit_type(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/menu-items', [
            'type' => 'simple',
            'price' => 5.99,
            'translations' => ['en' => ['name' => 'Fries', 'description' => 'Crispy']],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'simple')
            ->assertJsonPath('data.price', 5.99)
            ->assertJsonPath('data.translations.en.name', 'Fries')
            ->assertJsonMissingPath('data.combo_entries')
            ->assertJsonMissingPath('data.variant_option_groups');
    }

    public function test_catalog_combo_referencing_simple_and_variant_items(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $burger = $this->postJson('/api/menu-items', [
            'type' => 'simple',
            'translations' => ['en' => ['name' => 'Burger', 'description' => null]],
            'price' => 6.00,
        ], ['Authorization' => 'Bearer ' . $token]);
        $burger->assertStatus(201);
        $burgerUuid = $burger->json('data.uuid');

        $pizza = $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Pizza', 'description' => null]],
            'variant_option_groups' => [['name' => 'Size', 'values' => ['Small', 'Large']]],
            'variant_skus' => [
                ['option_values' => ['Size' => 'Small'], 'price' => 8.00],
                ['option_values' => ['Size' => 'Large'], 'price' => 12.00],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);
        $pizza->assertStatus(201);
        $pizzaUuid = $pizza->json('data.uuid');
        $pizzaSkus = $pizza->json('data.variant_skus');
        $largeSkuUuid = collect($pizzaSkus)->firstWhere('option_values.Size', 'Large')['uuid'];

        $response = $this->postJson('/api/menu-items', [
            'type' => 'combo',
            'combo_price' => 15.00,
            'translations' => ['en' => ['name' => 'Burger + Pizza', 'description' => null]],
            'combo_entries' => [
                ['menu_item_uuid' => $burgerUuid, 'quantity' => 1],
                ['menu_item_uuid' => $pizzaUuid, 'variant_uuid' => $largeSkuUuid, 'quantity' => 1],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'combo');
        $this->assertSame(15.0, (float) $response->json('data.combo_price'));
        $data = $response->json('data');
        $this->assertCount(2, $data['combo_entries']);
        $pizzaEntry = collect($data['combo_entries'])->firstWhere('menu_item_uuid', $pizzaUuid);
        $this->assertNotNull($pizzaEntry);
        $this->assertSame($largeSkuUuid, $pizzaEntry['variant_uuid']);
    }

    public function test_reject_combo_when_variant_item_missing_variant_uuid(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $pizza = $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Pizza', 'description' => null]],
            'variant_option_groups' => [['name' => 'Size', 'values' => ['Small']]],
            'variant_skus' => [['option_values' => ['Size' => 'Small'], 'price' => 8.00]],
        ], ['Authorization' => 'Bearer ' . $token]);
        $pizza->assertStatus(201);
        $pizzaUuid = $pizza->json('data.uuid');

        $response = $this->postJson('/api/menu-items', [
            'type' => 'combo',
            'combo_price' => 8.00,
            'translations' => ['en' => ['name' => 'Combo', 'description' => null]],
            'combo_entries' => [
                ['menu_item_uuid' => $pizzaUuid, 'quantity' => 1],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $variantMsg = $errors['combo_entries.0.variant_uuid'][0] ?? implode(' ', array_merge(...array_values($errors)));
        $this->assertStringContainsString('variant', (string) $variantMsg);
    }

    public function test_reject_combo_reference_nonexistent_item(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;
        $fakeUuid = '00000000-0000-0000-0000-000000000001';

        $response = $this->postJson('/api/menu-items', [
            'type' => 'combo',
            'combo_price' => 6.00,
            'translations' => ['en' => ['name' => 'Combo', 'description' => null]],
            'combo_entries' => [
                ['menu_item_uuid' => $fakeUuid, 'quantity' => 1],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $msg = (string) ($errors['combo_entries.0.menu_item_uuid'][0] ?? implode(' ', array_merge(...array_values($errors))));
        $this->assertTrue(str_contains($msg, 'does not exist') || str_contains($msg, 'not owned'), 'Expected message about missing/non-owned item: ' . $msg);
    }

    public function test_reject_combo_non_owned_item(): void
    {
        $userA = User::factory()->create(['email' => 'combo-user-a@test.com', 'email_verified_at' => now()]);
        $userB = User::factory()->create(['email' => 'combo-user-b@test.com', 'email_verified_at' => now()]);
        $this->assertNotSame($userA->id, $userB->id, 'Need two distinct users');

        $tokenA = $userA->createToken('auth')->plainTextToken;
        $tokenB = $userB->createToken('auth')->plainTextToken;

        // Item is created by user A (owner of the item)
        $createItem = $this->postJson('/api/menu-items', [
            'translations' => ['en' => ['name' => 'Burger', 'description' => null]],
            'price' => 6.00,
        ], ['Authorization' => 'Bearer ' . $tokenA]);
        $createItem->assertStatus(201);
        $itemUuid = $createItem->json('data.uuid');
        $this->assertSame($userA->id, MenuItem::query()->where('uuid', $itemUuid)->value('user_id'), 'Item must belong to user A');

        // User B tries to create a combo referencing user A's item — must be rejected
        $response = $this->actingAs($userB)->postJson('/api/menu-items', [
            'type' => 'combo',
            'combo_price' => 6.00,
            'translations' => ['en' => ['name' => 'Combo', 'description' => null]],
            'combo_entries' => [
                ['menu_item_uuid' => $itemUuid, 'quantity' => 1],
            ],
        ], ['Authorization' => 'Bearer ' . $tokenB]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $msg = (string) ($errors['combo_entries.0.menu_item_uuid'][0] ?? implode(' ', array_merge(...array_values($errors))));
        $this->assertTrue(str_contains($msg, 'does not exist') || str_contains($msg, 'not owned'), 'Expected message about non-owned or missing item: ' . $msg);
    }

    public function test_reject_variant_skus_incomplete_cartesian_product(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Pizza', 'description' => null]],
            'variant_option_groups' => [
                ['name' => 'Type', 'values' => ['A', 'B']],
                ['name' => 'Size', 'values' => ['S', 'L']],
            ],
            'variant_skus' => [
                ['option_values' => ['Type' => 'A', 'Size' => 'S'], 'price' => 8.00],
                ['option_values' => ['Type' => 'A', 'Size' => 'L'], 'price' => 12.00],
                ['option_values' => ['Type' => 'B', 'Size' => 'S'], 'price' => 9.00],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422);
        $msg = (string) $response->json('errors.variant_skus.0');
        $this->assertTrue(str_contains($msg, 'combination') || str_contains($msg, 'cover'), 'Expected Cartesian/product message: ' . $msg);
    }

    public function test_reject_variant_skus_duplicate_combination(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Pizza', 'description' => null]],
            'variant_option_groups' => [['name' => 'Size', 'values' => ['S', 'L']]],
            'variant_skus' => [
                ['option_values' => ['Size' => 'S'], 'price' => 8.00],
                ['option_values' => ['Size' => 'L'], 'price' => 12.00],
                ['option_values' => ['Size' => 'S'], 'price' => 7.00],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Duplicate', (string) $response->json('errors.variant_skus.0'));
    }

    public function test_update_catalog_item_change_variant_skus(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $create = $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Pizza', 'description' => null]],
            'variant_option_groups' => [['name' => 'Size', 'values' => ['S', 'L']]],
            'variant_skus' => [
                ['option_values' => ['Size' => 'S'], 'price' => 8.00],
                ['option_values' => ['Size' => 'L'], 'price' => 12.00],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);
        $create->assertStatus(201);
        $itemUuid = $create->json('data.uuid');

        $response = $this->patchJson('/api/menu-items/' . $itemUuid, [
            'variant_option_groups' => [['name' => 'Size', 'values' => ['S', 'L']]],
            'variant_skus' => [
                ['option_values' => ['Size' => 'S'], 'price' => 9.00],
                ['option_values' => ['Size' => 'L'], 'price' => 14.00],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'with_variants');
        $skus = $response->json('data.variant_skus');
        $this->assertCount(2, $skus);
        $prices = collect($skus)->map(fn ($s) => (float) $s['price'])->all();
        $this->assertContains(9.0, $prices);
        $this->assertContains(14.0, $prices);
    }

    public function test_update_catalog_item_change_combo_entries(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $a = $this->postJson('/api/menu-items', [
            'translations' => ['en' => ['name' => 'Item A', 'description' => null]],
            'price' => 3.00,
        ], ['Authorization' => 'Bearer ' . $token]);
        $a->assertStatus(201);
        $b = $this->postJson('/api/menu-items', [
            'translations' => ['en' => ['name' => 'Item B', 'description' => null]],
            'price' => 4.00,
        ], ['Authorization' => 'Bearer ' . $token]);
        $b->assertStatus(201);

        $combo = $this->postJson('/api/menu-items', [
            'type' => 'combo',
            'combo_price' => 6.00,
            'translations' => ['en' => ['name' => 'Combo', 'description' => null]],
            'combo_entries' => [
                ['menu_item_uuid' => $a->json('data.uuid'), 'quantity' => 1],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);
        $combo->assertStatus(201);
        $comboUuid = $combo->json('data.uuid');

        $response = $this->patchJson('/api/menu-items/' . $comboUuid, [
            'combo_entries' => [
                ['menu_item_uuid' => $a->json('data.uuid'), 'quantity' => 2],
                ['menu_item_uuid' => $b->json('data.uuid'), 'quantity' => 1],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'combo');
        $entries = $response->json('data.combo_entries');
        $this->assertCount(2, $entries);
        $quantities = array_column($entries, 'quantity');
        $this->assertContains(2, $quantities);
        $this->assertContains(1, $quantities);
    }

    public function test_list_catalog_menu_items_includes_type_combo_entries_and_variants(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $this->postJson('/api/menu-items', [
            'type' => 'simple',
            'translations' => ['en' => ['name' => 'Simple', 'description' => null]],
            'price' => 5.00,
        ], ['Authorization' => 'Bearer ' . $token]);
        $combo = $this->postJson('/api/menu-items', [
            'type' => 'combo',
            'combo_price' => 7.00,
            'translations' => ['en' => ['name' => 'Combo', 'description' => null]],
            'combo_entries' => [
                ['menu_item_uuid' => $this->postJson('/api/menu-items', [
                    'translations' => ['en' => ['name' => 'X', 'description' => null]],
                    'price' => 3,
                ], ['Authorization' => 'Bearer ' . $token])->json('data.uuid'), 'quantity' => 1],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);
        $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Variants', 'description' => null]],
            'variant_option_groups' => [['name' => 'Size', 'values' => ['S']]],
            'variant_skus' => [['option_values' => ['Size' => 'S'], 'price' => 6.00]],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response = $this->getJson('/api/menu-items', ['Authorization' => 'Bearer ' . $token]);
        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($data));
        $types = array_column($data, 'type');
        $this->assertContains('simple', $types);
        $this->assertContains('combo', $types);
        $this->assertContains('with_variants', $types);
        $comboItem = collect($data)->firstWhere('type', 'combo');
        $this->assertNotNull($comboItem);
        $this->assertArrayHasKey('combo_entries', $comboItem);
        $variantItem = collect($data)->firstWhere('type', 'with_variants');
        $this->assertNotNull($variantItem);
        $this->assertArrayHasKey('variant_option_groups', $variantItem);
        $this->assertArrayHasKey('variant_skus', $variantItem);
    }

    public function test_show_catalog_menu_item_returns_combo_entries_and_variant_skus(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $combo = $this->postJson('/api/menu-items', [
            'type' => 'combo',
            'combo_price' => 5.00,
            'translations' => ['en' => ['name' => 'Combo', 'description' => null]],
            'combo_entries' => [
                ['menu_item_uuid' => $this->postJson('/api/menu-items', [
                    'translations' => ['en' => ['name' => 'A', 'description' => null]],
                    'price' => 2,
                ], ['Authorization' => 'Bearer ' . $token])->json('data.uuid'), 'quantity' => 1],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);
        $comboUuid = $combo->json('data.uuid');

        $show = $this->getJson('/api/menu-items/' . $comboUuid, ['Authorization' => 'Bearer ' . $token]);
        $show->assertOk()
            ->assertJsonPath('data.type', 'combo')
            ->assertJsonStructure(['data' => ['combo_entries']]);
        $this->assertSame(5.0, (float) $show->json('data.combo_price'));
        $this->assertCount(1, $show->json('data.combo_entries'));

        $pizza = $this->postJson('/api/menu-items', [
            'type' => 'with_variants',
            'translations' => ['en' => ['name' => 'Pizza', 'description' => null]],
            'variant_option_groups' => [['name' => 'Size', 'values' => ['S']]],
            'variant_skus' => [['option_values' => ['Size' => 'S'], 'price' => 8.00]],
        ], ['Authorization' => 'Bearer ' . $token]);
        $pizzaUuid = $pizza->json('data.uuid');
        $showPizza = $this->getJson('/api/menu-items/' . $pizzaUuid, ['Authorization' => 'Bearer ' . $token]);
        $showPizza->assertOk()
            ->assertJsonPath('data.type', 'with_variants')
            ->assertJsonStructure(['data' => ['variant_option_groups', 'variant_skus']]);
        $this->assertCount(1, $showPizza->json('data.variant_option_groups'));
        $this->assertCount(1, $showPizza->json('data.variant_skus'));
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
