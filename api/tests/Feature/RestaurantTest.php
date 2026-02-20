<?php

namespace Tests\Feature;

use App\Models\MenuItemTag;
use App\Models\Restaurant;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('restaurant')]
class RestaurantTest extends TestCase
{
    private function createVerifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function createRestaurantForUser(User $user, array $overrides = []): Restaurant
    {
        $r = new Restaurant;
        $r->user_id = $user->id;
        $r->name = $overrides['name'] ?? 'Test Restaurant';
        $r->slug = $overrides['slug'] ?? 'test-restaurant-' . uniqid();
        $r->tagline = $overrides['tagline'] ?? null;
        $r->default_locale = $overrides['default_locale'] ?? 'en';
        $r->save();
        $r->languages()->create(['locale' => $r->default_locale]);

        return $r;
    }

    public function test_list_restaurants_returns_empty_for_new_user(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_list_restaurants_returns_owned_restaurant(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $restaurant->uuid)
            ->assertJsonPath('data.0.name', $restaurant->name)
            ->assertJsonPath('data.0.slug', $restaurant->slug)
            ->assertJsonStructure(['data' => [0 => ['uuid', 'name', 'slug', 'public_url', 'default_locale', 'languages', 'created_at', 'updated_at']]])
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_show_restaurant_returns_owned_restaurant(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.uuid', $restaurant->uuid)
            ->assertJsonPath('data.name', $restaurant->name)
            ->assertJsonMissingPath('data.id');
    }

    public function test_show_restaurant_returns_404_for_other_users_restaurant(): void
    {
        $owner = $this->createVerifiedUser();
        $other = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($owner);
        $token = $other->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Restaurant not found.');
    }

    public function test_create_restaurant_succeeds_and_returns_payload_without_internal_id(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants', [
            'name' => 'My Bistro',
            'tagline' => 'Fresh food',
            'default_locale' => 'en',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Restaurant created.')
            ->assertJsonPath('data.name', 'My Bistro')
            ->assertJsonPath('data.tagline', 'Fresh food')
            ->assertJsonPath('data.default_locale', 'en')
            ->assertJsonStructure(['data' => ['uuid', 'name', 'slug', 'public_url', 'languages', 'created_at', 'updated_at']])
            ->assertJsonMissingPath('data.id');

        $this->assertDatabaseHas('restaurants', ['name' => 'My Bistro', 'user_id' => $user->id]);
    }

    public function test_create_restaurant_with_operating_hours_persists_and_returns_in_payload(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;
        $operatingHours = [
            'sunday' => ['open' => false, 'slots' => []],
            'monday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '17:00']]],
            'tuesday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '12:00'], ['from' => '14:00', 'to' => '18:00']]],
            'wednesday' => ['open' => false, 'slots' => []],
            'thursday' => ['open' => true, 'slots' => [['from' => '10:00', 'to' => '22:00']]],
            'friday' => ['open' => true, 'slots' => [['from' => '10:00', 'to' => '23:00']]],
            'saturday' => ['open' => false, 'slots' => []],
        ];

        $response = $this->postJson('/api/restaurants', [
            'name' => 'Bistro With Hours',
            'default_locale' => 'en',
            'operating_hours' => $operatingHours,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Restaurant created.')
            ->assertJsonPath('data.name', 'Bistro With Hours')
            ->assertJsonPath('data.operating_hours.monday.open', true)
            ->assertJsonPath('data.operating_hours.monday.slots.0.from', '09:00')
            ->assertJsonPath('data.operating_hours.monday.slots.0.to', '17:00')
            ->assertJsonPath('data.operating_hours.tuesday.slots.1.from', '14:00')
            ->assertJsonPath('data.operating_hours.wednesday.open', false)
            ->assertJsonMissingPath('data.id');

        $restaurant = Restaurant::where('user_id', $user->id)->where('name', 'Bistro With Hours')->first();
        $this->assertNotNull($restaurant);
        $this->assertSame($operatingHours, $restaurant->operating_hours);
    }

    public function test_create_restaurant_operating_hours_rejects_overlapping_slots(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants', [
            'name' => 'Bad Hours',
            'default_locale' => 'en',
            'operating_hours' => [
                'monday' => [
                    'open' => true,
                    'slots' => [
                        ['from' => '09:00', 'to' => '12:00'],
                        ['from' => '11:00', 'to' => '14:00'],
                    ],
                ],
            ],
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['operating_hours']);
    }

    public function test_create_second_restaurant_returns_403_free_tier(): void
    {
        $user = $this->createVerifiedUser();
        $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants', [
            'name' => 'Second Place',
            'default_locale' => 'en',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Free tier allows one restaurant. Upgrade to add more.');
    }

    public function test_update_restaurant_succeeds(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'name' => 'Updated Name',
            'tagline' => 'New tagline',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Restaurant updated.')
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.tagline', 'New tagline')
            ->assertJsonPath('data.slug', $restaurant->slug);

        $restaurant->refresh();
        $this->assertSame('Updated Name', $restaurant->name);
        $this->assertSame('New tagline', $restaurant->tagline);
    }

    public function test_update_restaurant_with_operating_hours_persists_and_returns_in_payload(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $operatingHours = [
            'monday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '12:00'], ['from' => '14:00', 'to' => '21:00']]],
            'tuesday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '21:00']]],
            'wednesday' => ['open' => false, 'slots' => []],
        ];

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'operating_hours' => $operatingHours,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.operating_hours.monday.open', true)
            ->assertJsonPath('data.operating_hours.monday.slots.0.from', '09:00')
            ->assertJsonPath('data.operating_hours.monday.slots.0.to', '12:00')
            ->assertJsonPath('data.operating_hours.monday.slots.1.from', '14:00')
            ->assertJsonPath('data.operating_hours.wednesday.open', false);

        $restaurant->refresh();
        $this->assertSame($operatingHours, $restaurant->operating_hours);
    }

    public function test_update_restaurant_operating_hours_rejects_overlapping_slots(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'operating_hours' => [
                'monday' => [
                    'open' => true,
                    'slots' => [
                        ['from' => '09:00', 'to' => '12:00'],
                        ['from' => '11:00', 'to' => '14:00'],
                    ],
                ],
            ],
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['operating_hours']);
    }

    public function test_update_restaurant_returns_403_for_other_users_restaurant(): void
    {
        $owner = $this->createVerifiedUser();
        $other = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($owner);
        $token = $other->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'name' => 'Hacked',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_restaurant_succeeds(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->deleteJson('/api/restaurants/' . $restaurant->uuid, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
    }

    public function test_delete_restaurant_returns_403_for_other_user(): void
    {
        $owner = $this->createVerifiedUser();
        $other = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($owner);
        $token = $other->createToken('auth')->plainTextToken;

        $response = $this->deleteJson('/api/restaurants/' . $restaurant->uuid, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id]);
    }

    public function test_public_restaurant_by_slug_returns_200_with_data(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'public-bistro']);
        $restaurant->translations()->create(['locale' => 'en', 'description' => 'Nice place']);

        $response = $this->getJson('/api/public/restaurants/public-bistro');

        $response->assertOk()
            ->assertJsonPath('data.name', $restaurant->name)
            ->assertJsonPath('data.slug', 'public-bistro')
            ->assertJsonPath('data.description', 'Nice place')
            ->assertJsonPath('data.menu_items', [])
            ->assertJsonPath('data.operating_hours', null)
            ->assertJsonStructure(['data' => ['name', 'slug', 'logo_url', 'banner_url', 'default_locale', 'operating_hours', 'languages', 'locale', 'description', 'menu_items']]);
        $response->assertJsonMissingPath('data.id');
    }

    public function test_public_restaurant_by_slug_returns_operating_hours_when_set(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'hours-bistro']);
        $operatingHours = [
            'monday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '12:00'], ['from' => '14:00', 'to' => '21:00']]],
            'tuesday' => ['open' => true, 'slots' => [['from' => '09:00', 'to' => '21:00']]],
            'wednesday' => ['open' => false, 'slots' => []],
        ];
        $restaurant->operating_hours = $operatingHours;
        $restaurant->save();

        $response = $this->getJson('/api/public/restaurants/hours-bistro');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'hours-bistro')
            ->assertJsonPath('data.operating_hours.monday.open', true)
            ->assertJsonPath('data.operating_hours.monday.slots.0.from', '09:00')
            ->assertJsonPath('data.operating_hours.monday.slots.0.to', '12:00')
            ->assertJsonPath('data.operating_hours.monday.slots.1.from', '14:00')
            ->assertJsonPath('data.operating_hours.tuesday.slots.0.to', '21:00')
            ->assertJsonPath('data.operating_hours.wednesday.open', false);
        $response->assertJsonMissingPath('data.id');
    }

    public function test_public_restaurant_by_slug_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/public/restaurants/nonexistent-slug-12345');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Restaurant not found.');
    }

    public function test_public_restaurant_by_slug_excludes_inactive_menu_items(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'public-inactive-items']);
        $activeItem = $restaurant->menuItems()->create(['sort_order' => 0, 'is_active' => true, 'is_available' => true]);
        $activeItem->translations()->create(['locale' => 'en', 'name' => 'Visible Item', 'description' => null]);
        $unavailableItem = $restaurant->menuItems()->create(['sort_order' => 1, 'is_active' => true, 'is_available' => false]);
        $unavailableItem->translations()->create(['locale' => 'en', 'name' => 'Unavailable Item', 'description' => null]);
        $inactiveItem = $restaurant->menuItems()->create(['sort_order' => 2, 'is_active' => false]);
        $inactiveItem->translations()->create(['locale' => 'en', 'name' => 'Hidden Item', 'description' => null]);

        $response = $this->getJson('/api/public/restaurants/public-inactive-items');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'public-inactive-items');
        $menuItems = $response->json('data.menu_items');
        $this->assertCount(2, $menuItems);
        foreach ($menuItems as $menuItem) {
            $this->assertArrayHasKey('is_available', $menuItem);
        }
        $visible = collect($menuItems)->firstWhere('name', 'Visible Item');
        $this->assertNotNull($visible);
        $this->assertTrue($visible['is_available']);
        $unavailable = collect($menuItems)->firstWhere('name', 'Unavailable Item');
        $this->assertNotNull($unavailable);
        $this->assertFalse($unavailable['is_available']);
        $uuids = array_column($menuItems, 'uuid');
        $this->assertContains($activeItem->uuid, $uuids);
        $this->assertContains($unavailableItem->uuid, $uuids);
        $this->assertNotContains($inactiveItem->uuid, $uuids);
    }

    public function test_public_restaurant_menu_items_include_tags_with_uuid_color_icon_text(): void
    {
        $tag = MenuItemTag::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'text' => 'Spicy',
            'color' => '#dc2626',
            'icon' => 'local_fire_department',
            'user_id' => null,
        ]);
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'tags-bistro']);
        $item = $restaurant->menuItems()->create(['sort_order' => 0, 'is_active' => true, 'is_available' => true]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Hot Wings', 'description' => null]);
        $item->menuItemTags()->attach($tag->id);

        $response = $this->getJson('/api/public/restaurants/tags-bistro');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'tags-bistro');
        $menuItems = $response->json('data.menu_items');
        $this->assertCount(1, $menuItems);
        $this->assertArrayHasKey('tags', $menuItems[0]);
        $tags = $menuItems[0]['tags'];
        $this->assertIsArray($tags);
        $this->assertCount(1, $tags);
        $this->assertSame($tag->uuid, $tags[0]['uuid']);
        $this->assertSame($tag->color, $tags[0]['color']);
        $this->assertSame($tag->icon, $tags[0]['icon']);
        $this->assertSame($tag->text, $tags[0]['text']);
    }

    public function test_public_restaurant_menu_items_include_availability(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'availability-bistro']);
        $availability = [
            'friday' => ['open' => true, 'slots' => [['from' => '18:00', 'to' => '23:00']]],
            'saturday' => ['open' => true, 'slots' => [['from' => '12:00', 'to' => '23:00']]],
        ];
        $itemWithAvailability = $restaurant->menuItems()->create(['sort_order' => 0, 'is_active' => true, 'is_available' => true, 'availability' => $availability]);
        $itemWithAvailability->translations()->create(['locale' => 'en', 'name' => 'Weekend Special', 'description' => null]);
        $itemNullAvailability = $restaurant->menuItems()->create(['sort_order' => 1, 'is_active' => true, 'is_available' => true, 'availability' => null]);
        $itemNullAvailability->translations()->create(['locale' => 'en', 'name' => 'Always Available', 'description' => null]);

        $response = $this->getJson('/api/public/restaurants/availability-bistro');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'availability-bistro');
        $menuItems = $response->json('data.menu_items');
        $this->assertCount(2, $menuItems);
        $this->assertArrayHasKey('availability', $menuItems[0]);
        $this->assertSame($availability, $menuItems[0]['availability']);
        $this->assertArrayHasKey('availability', $menuItems[1]);
        $this->assertNull($menuItems[1]['availability']);
    }

    public function test_restaurant_endpoints_require_authentication(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);

        $this->getJson('/api/restaurants')->assertStatus(401);
        $this->getJson('/api/restaurants/' . $restaurant->uuid)->assertStatus(401);
        $this->postJson('/api/restaurants', ['name' => 'X', 'default_locale' => 'en'])->assertStatus(401);
        $this->patchJson('/api/restaurants/' . $restaurant->uuid, ['name' => 'Y'])->assertStatus(401);
        $this->deleteJson('/api/restaurants/' . $restaurant->uuid)->assertStatus(401);
    }

    public function test_serve_logo_returns_404_when_no_logo_uploaded(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/logo');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'File not found.');
    }

    public function test_serve_banner_returns_404_when_no_banner_uploaded(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/banner');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'File not found.');
    }
}
