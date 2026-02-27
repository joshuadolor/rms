<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantContact;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('restaurant-contacts')]
class RestaurantContactTest extends TestCase
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

    private function createContact(Restaurant $restaurant, array $overrides = []): RestaurantContact
    {
        $type = $overrides['type'] ?? 'mobile';
        $value = $overrides['value'] ?? $overrides['number'] ?? '+1234567890';
        $isPhone = in_array($type, RestaurantContact::TYPES_PHONE, true);

        $c = new RestaurantContact;
        $c->restaurant_id = $restaurant->id;
        $c->type = $type;
        $c->value = $value;
        $c->number = $isPhone ? $value : null;
        $c->label = $overrides['label'] ?? null;
        $c->is_active = $overrides['is_active'] ?? true;
        $c->save();

        return $c;
    }

    public function test_owner_can_list_contacts(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'contacts-bistro']);
        $this->createContact($restaurant, ['type' => 'whatsapp', 'number' => '+15551234567', 'label' => 'Main']);
        $this->createContact($restaurant, ['type' => 'mobile', 'number' => '+15559876543', 'is_active' => false]);

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'whatsapp')
            ->assertJsonPath('data.0.value', '+15551234567')
            ->assertJsonPath('data.0.number', '+15551234567')
            ->assertJsonPath('data.0.label', 'Main')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonStructure(['data' => [0 => ['uuid', 'type', 'value', 'number', 'label', 'is_active', 'created_at', 'updated_at']]])
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_owner_can_create_contact(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'create-contact-bistro']);

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'type' => 'whatsapp',
            'value' => '+31612345678',
            'label' => 'Delivery',
            'is_active' => true,
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Contact created.')
            ->assertJsonPath('data.type', 'whatsapp')
            ->assertJsonPath('data.value', '+31612345678')
            ->assertJsonPath('data.number', '+31612345678')
            ->assertJsonPath('data.label', 'Delivery')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => ['uuid', 'type', 'value', 'number', 'label', 'is_active', 'created_at', 'updated_at']])
            ->assertJsonMissingPath('data.id');

        $this->assertDatabaseHas('restaurant_contacts', [
            'restaurant_id' => $restaurant->id,
            'type' => 'whatsapp',
            'value' => '+31612345678',
            'label' => 'Delivery',
            'is_active' => true,
        ]);
    }

    public function test_owner_can_show_contact(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'show-contact-bistro']);
        $contact = $this->createContact($restaurant, ['type' => 'phone', 'number' => '+15550001111', 'label' => 'Reception']);

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.uuid', $contact->uuid)
            ->assertJsonPath('data.type', 'phone')
            ->assertJsonPath('data.value', '+15550001111')
            ->assertJsonPath('data.number', '+15550001111')
            ->assertJsonPath('data.label', 'Reception')
            ->assertJsonMissingPath('data.id');
    }

    public function test_owner_can_update_contact(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'update-contact-bistro']);
        $contact = $this->createContact($restaurant, ['type' => 'mobile', 'number' => '+15552223333', 'is_active' => true]);

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, [
            'label' => 'Kitchen',
            'is_active' => false,
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Contact updated.')
            ->assertJsonPath('data.label', 'Kitchen')
            ->assertJsonPath('data.is_active', false);

        $contact->refresh();
        $this->assertSame('Kitchen', $contact->label);
        $this->assertFalse($contact->is_active);
    }

    public function test_owner_can_delete_contact(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'delete-contact-bistro']);
        $contact = $this->createContact($restaurant);

        $response = $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, [], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('restaurant_contacts', ['id' => $contact->id]);
    }

    public function test_contacts_returns_404_for_other_owners_restaurant(): void
    {
        $owner = $this->createVerifiedUser();
        $other = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($owner, ['slug' => 'owned-bistro']);

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'Authorization' => 'Bearer ' . $other->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(404)->assertJsonPath('message', 'Restaurant not found.');
    }

    public function test_create_contact_returns_422_for_invalid_type(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'invalid-type-bistro']);

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'type' => 'invalid_type',
            'value' => '+15551234567',
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_update_contact_returns_422_for_invalid_type(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'update-invalid-type-bistro']);
        $contact = $this->createContact($restaurant, ['type' => 'mobile', 'number' => '+15551234567']);

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, [
            'type' => 'invalid_type',
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_create_contact_returns_422_when_type_missing(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'create-missing-type-bistro']);

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'value' => '+15551234567',
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_create_contact_returns_422_when_value_missing(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'create-missing-value-bistro']);

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'type' => 'mobile',
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    public function test_create_contact_returns_422_when_value_exceeds_max_length(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'create-long-value-bistro']);

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'type' => 'mobile',
            'value' => str_repeat('+', 501),
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    public function test_create_contact_returns_422_when_label_exceeds_max_length(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'create-long-label-bistro']);

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'type' => 'mobile',
            'value' => '+15551234567',
            'label' => str_repeat('a', 101),
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('label');
    }

    public function test_update_contact_returns_422_when_value_exceeds_max_length(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'update-long-value-bistro']);
        $contact = $this->createContact($restaurant, ['type' => 'mobile', 'value' => '+15551234567']);

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, [
            'value' => str_repeat('+', 501),
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    public function test_update_contact_returns_422_when_label_exceeds_max_length(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'update-long-label-bistro']);
        $contact = $this->createContact($restaurant, ['type' => 'mobile', 'value' => '+15551234567']);

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, [
            'label' => str_repeat('a', 101),
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('label');
    }

    public function test_public_restaurant_show_returns_empty_contacts_when_all_inactive(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'public-all-inactive-bistro']);
        $this->createContact($restaurant, ['type' => 'mobile', 'number' => '+15550000001', 'is_active' => false]);
        $this->createContact($restaurant, ['type' => 'whatsapp', 'number' => '+15550000002', 'is_active' => false]);

        $response = $this->getJson('/api/public/restaurants/public-all-inactive-bistro');

        $response->assertStatus(200);
        $contacts = $response->json('data.contacts');
        $this->assertIsArray($contacts);
        $this->assertCount(0, $contacts);
    }

    public function test_public_restaurant_show_includes_only_active_contacts(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'public-contacts-bistro']);
        $this->createContact($restaurant, ['type' => 'whatsapp', 'number' => '+15551111111', 'label' => 'Main', 'is_active' => true]);
        $this->createContact($restaurant, ['type' => 'mobile', 'number' => '+15552222222', 'label' => 'Hidden', 'is_active' => false]);

        $response = $this->getJson('/api/public/restaurants/public-contacts-bistro');

        $response->assertStatus(200);
        $contacts = $response->json('data.contacts');
        $this->assertIsArray($contacts);
        $this->assertCount(1, $contacts);
        $this->assertSame('whatsapp', $contacts[0]['type']);
        $this->assertSame('+15551111111', $contacts[0]['value']);
        $this->assertSame('+15551111111', $contacts[0]['number']);
        $this->assertSame('Main', $contacts[0]['label']);
        $this->assertArrayHasKey('uuid', $contacts[0]);
        $this->assertArrayHasKey('value', $contacts[0]);
        $this->assertArrayNotHasKey('id', $contacts[0]);
        $this->assertArrayNotHasKey('is_active', $contacts[0]); // public payload omits is_active
    }

    public function test_owner_can_create_link_type_contact(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'link-contact-bistro']);

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'type' => 'instagram',
            'value' => 'https://instagram.com/restaurant',
            'label' => 'Our Instagram',
            'is_active' => true,
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'instagram')
            ->assertJsonPath('data.value', 'https://instagram.com/restaurant')
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.label', 'Our Instagram');
        $this->assertDatabaseHas('restaurant_contacts', [
            'restaurant_id' => $restaurant->id,
            'type' => 'instagram',
            'value' => 'https://instagram.com/restaurant',
        ]);
    }

    public function test_create_link_type_returns_422_when_value_not_valid_url(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'link-invalid-bistro']);

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'type' => 'facebook',
            'value' => 'not-a-valid-url',
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    public function test_update_link_type_returns_422_when_only_value_sent_and_not_valid_url(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'update-link-invalid-bistro']);
        $contact = $this->createContact($restaurant, [
            'type' => 'website',
            'value' => 'https://example.com',
            'number' => null,
        ]);

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, [
            'value' => 'not-a-valid-url',
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    public function test_update_link_type_succeeds_when_only_value_sent_with_valid_url(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'update-link-valid-bistro']);
        $contact = $this->createContact($restaurant, [
            'type' => 'facebook',
            'value' => 'https://facebook.com/old-page',
            'number' => null,
        ]);

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, [
            'value' => 'https://facebook.com/new-page',
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.type', 'facebook')
            ->assertJsonPath('data.value', 'https://facebook.com/new-page')
            ->assertJsonPath('data.number', null);
        $contact->refresh();
        $this->assertSame('https://facebook.com/new-page', $contact->value);
    }

    public function test_update_contact_accepts_number_for_backward_compat(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'update-backward-compat-bistro']);
        $contact = $this->createContact($restaurant, ['type' => 'mobile', 'value' => '+15550001111', 'number' => '+15550001111']);

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, [
            'number' => '+15559990000',
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.value', '+15559990000')
            ->assertJsonPath('data.number', '+15559990000');
        $contact->refresh();
        $this->assertSame('+15559990000', $contact->value);
        $this->assertSame('+15559990000', $contact->number);
    }

    public function test_create_contact_accepts_number_for_backward_compat(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'backward-compat-bistro']);

        $response = $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', [
            'type' => 'phone',
            'number' => '+15559998888',
            'label' => 'Reception',
        ], [
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.value', '+15559998888')
            ->assertJsonPath('data.number', '+15559998888');
        $this->assertDatabaseHas('restaurant_contacts', ['value' => '+15559998888']);
    }

    public function test_contacts_require_auth(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'auth-contacts-bistro']);
        $contact = $this->createContact($restaurant);

        $this->getJson('/api/restaurants/' . $restaurant->uuid . '/contacts')->assertStatus(401);
        $this->postJson('/api/restaurants/' . $restaurant->uuid . '/contacts', ['type' => 'mobile', 'value' => '+1'])->assertStatus(401);
        $this->getJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid)->assertStatus(401);
        $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid, ['label' => 'X'])->assertStatus(401);
        $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/contacts/' . $contact->uuid)->assertStatus(401);
    }
}
