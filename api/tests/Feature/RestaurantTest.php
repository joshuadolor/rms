<?php

namespace Tests\Feature;

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
            ->assertJsonStructure(['data' => ['name', 'slug', 'logo_url', 'banner_url', 'default_locale', 'languages', 'locale', 'description', 'menu_items']]);
        $response->assertJsonMissingPath('data.id');
    }

    public function test_public_restaurant_by_slug_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/public/restaurants/nonexistent-slug-12345');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Restaurant not found.');
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
