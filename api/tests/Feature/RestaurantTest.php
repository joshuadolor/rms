<?php

namespace Tests\Feature;

use App\Models\ComboEntry;
use App\Models\MenuItem;
use App\Models\MenuItemTag;
use App\Models\MenuItemVariantOptionGroup;
use App\Models\MenuItemVariantSku;
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
            ->assertJsonPath('data.template', 'default')
            ->assertJsonStructure(['data' => ['uuid', 'name', 'slug', 'template', 'public_url', 'languages', 'created_at', 'updated_at']])
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

    public function test_create_restaurant_with_template_persists_and_returns_in_payload(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants', [
            'name' => 'Minimal Template Place',
            'default_locale' => 'en',
            'template' => 'template-2',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.template', 'template-2');
        $this->assertDatabaseHas('restaurants', ['name' => 'Minimal Template Place', 'template' => 'template-2']);
    }

    public function test_create_restaurant_rejects_invalid_template(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/restaurants', [
            'name' => 'Bad Template Place',
            'default_locale' => 'en',
            'template' => 'invalid-template',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['template']);
    }

    public function test_create_restaurant_with_year_established_persists_and_returns_it(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;
        $year = 1995;

        $response = $this->postJson('/api/restaurants', [
            'name' => 'Established Bistro',
            'default_locale' => 'en',
            'year_established' => $year,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Restaurant created.')
            ->assertJsonPath('data.name', 'Established Bistro')
            ->assertJsonPath('data.year_established', $year)
            ->assertJsonMissingPath('data.id');

        $this->assertDatabaseHas('restaurants', ['name' => 'Established Bistro', 'user_id' => $user->id, 'year_established' => $year]);
    }

    public function test_create_restaurant_rejects_year_established_out_of_range(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;
        $maxAllowed = (int) date('Y') + 1;

        $tooLow = $this->postJson('/api/restaurants', [
            'name' => 'Too Old',
            'default_locale' => 'en',
            'year_established' => 1799,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $tooLow->assertStatus(422)->assertJsonValidationErrors(['year_established']);

        $tooHigh = $this->postJson('/api/restaurants', [
            'name' => 'Too Future',
            'default_locale' => 'en',
            'year_established' => $maxAllowed + 1,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $tooHigh->assertStatus(422)->assertJsonValidationErrors(['year_established']);
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

    public function test_update_restaurant_template_persists_and_returns_in_payload(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'template' => 'template-2',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.template', 'template-2');
        $restaurant->refresh();
        $this->assertSame('template-2', $restaurant->template);
    }

    public function test_update_restaurant_rejects_invalid_template(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'template' => 'invalid-template',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['template']);
    }

    public function test_update_restaurant_year_established_persists_and_null_clears(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'year_established' => 1988,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.year_established', 1988);
        $restaurant->refresh();
        $this->assertSame(1988, $restaurant->year_established);

        $clearResponse = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'year_established' => null,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $clearResponse->assertOk()
            ->assertJsonPath('data.year_established', null);
        $restaurant->refresh();
        $this->assertNull($restaurant->year_established);
    }

    public function test_update_restaurant_rejects_year_established_out_of_range(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;
        $maxAllowed = (int) date('Y') + 1;

        $tooLow = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'year_established' => 1799,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $tooLow->assertStatus(422)->assertJsonValidationErrors(['year_established']);

        $tooHigh = $this->patchJson('/api/restaurants/' . $restaurant->uuid, [
            'year_established' => $maxAllowed + 1,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $tooHigh->assertStatus(422)->assertJsonValidationErrors(['year_established']);
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
            ->assertJsonPath('data.template', 'template-1')
            ->assertJsonPath('data.description', 'Nice place')
            ->assertJsonPath('data.menu_items', [])
            ->assertJsonPath('data.menu_groups', [])
            ->assertJsonPath('data.operating_hours', null)
            ->assertJsonStructure(['data' => ['name', 'slug', 'template', 'logo_url', 'banner_url', 'default_locale', 'operating_hours', 'languages', 'locale', 'description', 'menu_items', 'menu_groups']]);
        $response->assertJsonMissingPath('data.id');
    }

    public function test_public_restaurant_by_slug_returns_template_from_restaurant(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'minimal-public-bistro']);
        $restaurant->template = 'minimal';
        $restaurant->save();

        $response = $this->getJson('/api/public/restaurants/minimal-public-bistro');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'minimal-public-bistro')
            ->assertJsonPath('data.template', 'template-2');
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

    public function test_public_restaurant_includes_only_simple_combo_with_variants_menu_item_types(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'types-bistro']);

        $simpleItem = $restaurant->menuItems()->create([
            'sort_order' => 0,
            'is_active' => true,
            'is_available' => true,
            'type' => MenuItem::TYPE_SIMPLE,
        ]);
        $simpleItem->translations()->create(['locale' => 'en', 'name' => 'Simple Dish', 'description' => null]);

        $comboItem = $restaurant->menuItems()->create([
            'sort_order' => 1,
            'is_active' => true,
            'is_available' => true,
            'type' => MenuItem::TYPE_COMBO,
            'combo_price' => 12.00,
        ]);
        $comboItem->translations()->create(['locale' => 'en', 'name' => 'Combo Meal', 'description' => null]);
        ComboEntry::query()->create([
            'combo_menu_item_id' => $comboItem->id,
            'referenced_menu_item_id' => $simpleItem->id,
            'variant_id' => null,
            'quantity' => 1,
            'modifier_label' => null,
            'sort_order' => 0,
        ]);

        $variantItem = $restaurant->menuItems()->create([
            'sort_order' => 2,
            'is_active' => true,
            'is_available' => true,
            'type' => MenuItem::TYPE_WITH_VARIANTS,
        ]);
        $variantItem->translations()->create(['locale' => 'en', 'name' => 'Pizza', 'description' => null]);
        MenuItemVariantOptionGroup::query()->create([
            'menu_item_id' => $variantItem->id,
            'name' => 'Size',
            'values' => ['S', 'M'],
            'sort_order' => 0,
        ]);
        MenuItemVariantSku::query()->create([
            'menu_item_id' => $variantItem->id,
            'option_values' => ['Size' => 'S'],
            'price' => 8.00,
        ]);

        $otherTypeItem = $restaurant->menuItems()->create([
            'sort_order' => 3,
            'is_active' => true,
            'is_available' => true,
            'type' => 'other',
        ]);
        $otherTypeItem->translations()->create(['locale' => 'en', 'name' => 'Excluded Type Item', 'description' => null]);

        $response = $this->getJson('/api/public/restaurants/types-bistro');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'types-bistro');
        $menuItems = $response->json('data.menu_items');
        $this->assertCount(3, $menuItems, 'Only simple, combo, and with_variants should be returned');
        $types = array_column($menuItems, 'type');
        $this->assertContains(MenuItem::TYPE_SIMPLE, $types);
        $this->assertContains(MenuItem::TYPE_COMBO, $types);
        $this->assertContains(MenuItem::TYPE_WITH_VARIANTS, $types);
        $this->assertNotContains('other', $types);
        $uuids = array_column($menuItems, 'uuid');
        $this->assertNotContains($otherTypeItem->uuid, $uuids, 'Item with type "other" must be excluded');
    }

    public function test_public_restaurant_each_menu_item_has_type_field(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'type-field-bistro']);
        $restaurant->menuItems()->create([
            'sort_order' => 0,
            'is_active' => true,
            'is_available' => true,
        ])->translations()->create(['locale' => 'en', 'name' => 'Item', 'description' => null]);

        $response = $this->getJson('/api/public/restaurants/type-field-bistro');

        $response->assertOk();
        $menuItems = $response->json('data.menu_items');
        $this->assertCount(1, $menuItems);
        foreach ($menuItems as $item) {
            $this->assertArrayHasKey('type', $item, 'Each menu item must include a type field');
            $this->assertContains($item['type'], [MenuItem::TYPE_SIMPLE, MenuItem::TYPE_COMBO, MenuItem::TYPE_WITH_VARIANTS], 'type must be one of simple, combo, with_variants');
        }
    }

    public function test_public_restaurant_combo_item_includes_combo_entries_shape(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'combo-shape-bistro']);

        $burger = $restaurant->menuItems()->create(['sort_order' => 0, 'is_active' => true, 'is_available' => true]);
        $burger->translations()->create(['locale' => 'en', 'name' => 'Burger', 'description' => null]);
        $fries = $restaurant->menuItems()->create(['sort_order' => 1, 'is_active' => true, 'is_available' => true]);
        $fries->translations()->create(['locale' => 'en', 'name' => 'Fries', 'description' => null]);

        $combo = $restaurant->menuItems()->create([
            'sort_order' => 2,
            'is_active' => true,
            'is_available' => true,
            'type' => MenuItem::TYPE_COMBO,
            'combo_price' => 10.00,
        ]);
        $combo->translations()->create(['locale' => 'en', 'name' => 'Burger & Fries', 'description' => null]);
        ComboEntry::query()->create([
            'combo_menu_item_id' => $combo->id,
            'referenced_menu_item_id' => $burger->id,
            'variant_id' => null,
            'quantity' => 1,
            'modifier_label' => null,
            'sort_order' => 0,
        ]);
        ComboEntry::query()->create([
            'combo_menu_item_id' => $combo->id,
            'referenced_menu_item_id' => $fries->id,
            'variant_id' => null,
            'quantity' => 1,
            'modifier_label' => 'Extra salt',
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/public/restaurants/combo-shape-bistro');

        $response->assertOk();
        $menuItems = $response->json('data.menu_items');
        $comboPayload = collect($menuItems)->firstWhere('type', MenuItem::TYPE_COMBO);
        $this->assertNotNull($comboPayload, 'Combo item must be present');
        $this->assertArrayHasKey('combo_entries', $comboPayload);
        $entries = $comboPayload['combo_entries'];
        $this->assertIsArray($entries);
        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertArrayHasKey('referenced_item_uuid', $entry);
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('quantity', $entry);
            $this->assertArrayHasKey('modifier_label', $entry);
            $this->assertArrayHasKey('variant_uuid', $entry);
            $this->assertIsInt($entry['quantity']);
            $this->assertFalse(array_key_exists('id', $entry), 'combo_entries must not contain internal id');
        }
        $refUuids = array_column($entries, 'referenced_item_uuid');
        $this->assertContains($burger->uuid, $refUuids);
        $this->assertContains($fries->uuid, $refUuids);
        $entryWithModifier = collect($entries)->firstWhere('modifier_label', 'Extra salt');
        $this->assertNotNull($entryWithModifier);
        $this->assertSame($fries->uuid, $entryWithModifier['referenced_item_uuid']);
    }

    public function test_public_restaurant_with_variants_item_includes_variant_option_groups_and_skus(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'variants-shape-bistro']);

        $pizza = $restaurant->menuItems()->create([
            'sort_order' => 0,
            'is_active' => true,
            'is_available' => true,
            'type' => MenuItem::TYPE_WITH_VARIANTS,
        ]);
        $pizza->translations()->create(['locale' => 'en', 'name' => 'Pizza', 'description' => null]);
        MenuItemVariantOptionGroup::query()->create([
            'menu_item_id' => $pizza->id,
            'name' => 'Size',
            'values' => ['Small', 'Large'],
            'sort_order' => 0,
        ]);
        $skuSmall = MenuItemVariantSku::query()->create([
            'menu_item_id' => $pizza->id,
            'option_values' => ['Size' => 'Small'],
            'price' => 9.00,
        ]);
        MenuItemVariantSku::query()->create([
            'menu_item_id' => $pizza->id,
            'option_values' => ['Size' => 'Large'],
            'price' => 12.00,
            'image_url' => null,
        ]);

        $response = $this->getJson('/api/public/restaurants/variants-shape-bistro');

        $response->assertOk();
        $menuItems = $response->json('data.menu_items');
        $variantPayload = collect($menuItems)->firstWhere('type', MenuItem::TYPE_WITH_VARIANTS);
        $this->assertNotNull($variantPayload, 'With_variants item must be present');
        $this->assertArrayHasKey('variant_option_groups', $variantPayload);
        $this->assertArrayHasKey('variant_skus', $variantPayload);
        $groups = $variantPayload['variant_option_groups'];
        $this->assertIsArray($groups);
        $this->assertCount(1, $groups);
        $this->assertSame('Size', $groups[0]['name']);
        $this->assertSame(['Small', 'Large'], $groups[0]['values']);
        $skus = $variantPayload['variant_skus'];
        $this->assertIsArray($skus);
        $this->assertCount(2, $skus);
        foreach ($skus as $sku) {
            $this->assertArrayHasKey('uuid', $sku);
            $this->assertArrayHasKey('option_values', $sku);
            $this->assertArrayHasKey('price', $sku);
            $this->assertArrayHasKey('image_url', $sku);
            $this->assertFalse(array_key_exists('id', $sku), 'variant_skus must not contain internal id');
        }
        $this->assertContains($skuSmall->uuid, array_column($skus, 'uuid'));
    }

    public function test_public_restaurant_ending_variant_item_exposed_as_simple(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'ending-variant-bistro']);

        $catalogItem = MenuItem::query()->create([
            'user_id' => $user->id,
            'restaurant_id' => null,
            'sort_order' => 0,
            'type' => MenuItem::TYPE_WITH_VARIANTS,
        ]);
        $catalogItem->translations()->create(['locale' => 'en', 'name' => 'Catalog Pizza', 'description' => null]);
        MenuItemVariantOptionGroup::query()->create([
            'menu_item_id' => $catalogItem->id,
            'name' => 'Size',
            'values' => ['Small'],
            'sort_order' => 0,
        ]);
        $catalogSku = MenuItemVariantSku::query()->create([
            'menu_item_id' => $catalogItem->id,
            'option_values' => ['Size' => 'Small'],
            'price' => 7.50,
        ]);

        $restaurantItem = $restaurant->menuItems()->create([
            'sort_order' => 0,
            'is_active' => true,
            'is_available' => true,
            'source_menu_item_uuid' => $catalogItem->uuid,
            'source_variant_uuid' => $catalogSku->uuid,
        ]);
        $restaurantItem->translations()->create(['locale' => 'en', 'name' => 'Pizza Small', 'description' => null]);

        $response = $this->getJson('/api/public/restaurants/ending-variant-bistro');

        $response->assertOk();
        $menuItems = $response->json('data.menu_items');
        $this->assertCount(1, $menuItems);
        $item = $menuItems[0];
        $this->assertSame(MenuItem::TYPE_SIMPLE, $item['type'], 'Ending variant item must be exposed as type simple');
        $this->assertArrayNotHasKey('variant_option_groups', $item);
        $this->assertArrayNotHasKey('variant_skus', $item);
    }

    public function test_public_restaurant_response_has_no_internal_id(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'no-id-bistro']);
        $item = $restaurant->menuItems()->create(['sort_order' => 0, 'is_active' => true, 'is_available' => true]);
        $item->translations()->create(['locale' => 'en', 'name' => 'Dish', 'description' => null]);

        $response = $this->getJson('/api/public/restaurants/no-id-bistro');

        $response->assertOk()
            ->assertJsonMissingPath('data.id');
        $data = $response->json('data');
        $this->assertNotNull($data);
        $this->assertPublicPayloadHasNoInternalId($data);
    }

    private function assertPublicPayloadHasNoInternalId(array $data): void
    {
        $this->assertFalse(array_key_exists('id', $data), 'Public response must not contain internal id at any level');
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $i => $nested) {
                    if (is_array($nested)) {
                        $this->assertPublicPayloadHasNoInternalId($nested);
                    }
                }
            }
        }
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
