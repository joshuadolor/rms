<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\Restaurant;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('feedback')]
class FeedbackTest extends TestCase
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

    private function createFeedback(Restaurant $restaurant, array $overrides = []): Feedback
    {
        $f = new Feedback;
        $f->restaurant_id = $restaurant->id;
        $f->rating = $overrides['rating'] ?? 5;
        $f->text = $overrides['text'] ?? 'Great food!';
        $f->name = $overrides['name'] ?? 'Jane Doe';
        $f->is_approved = $overrides['is_approved'] ?? false;
        $f->save();

        return $f;
    }

    // --- Public submit (no auth) ---

    public function test_public_submit_feedback_success_returns_201_and_payload_with_uuid_no_id(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'feedback-bistro']);

        $response = $this->postJson('/api/public/restaurants/feedback-bistro/feedback', [
            'rating' => 4,
            'text' => 'Loved the service.',
            'name' => 'John Smith',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Thank you for your feedback.')
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.text', 'Loved the service.')
            ->assertJsonPath('data.name', 'John Smith')
            ->assertJsonPath('data.is_approved', false)
            ->assertJsonStructure(['data' => ['uuid', 'rating', 'text', 'name', 'is_approved', 'created_at']])
            ->assertJsonMissingPath('data.id');

        $this->assertDatabaseHas('feedbacks', [
            'restaurant_id' => $restaurant->id,
            'rating' => 4,
            'text' => 'Loved the service.',
            'name' => 'John Smith',
            'is_approved' => false,
        ]);
    }

    public function test_public_submit_feedback_returns_404_for_unknown_slug(): void
    {
        $response = $this->postJson('/api/public/restaurants/nonexistent-slug-xyz/feedback', [
            'rating' => 5,
            'text' => 'Good',
            'name' => 'Guest',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Restaurant not found.');
    }

    public function test_public_submit_feedback_returns_422_when_rating_invalid(): void
    {
        $user = $this->createVerifiedUser();
        $this->createRestaurantForUser($user, ['slug' => 'rating-bistro']);

        $response = $this->postJson('/api/public/restaurants/rating-bistro/feedback', [
            'rating' => 0,
            'text' => 'Okay',
            'name' => 'Guest',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_public_submit_feedback_returns_422_when_rating_out_of_range(): void
    {
        $user = $this->createVerifiedUser();
        $this->createRestaurantForUser($user, ['slug' => 'rating-bistro2']);

        $response = $this->postJson('/api/public/restaurants/rating-bistro2/feedback', [
            'rating' => 6,
            'text' => 'Okay',
            'name' => 'Guest',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_public_submit_feedback_returns_422_when_text_or_name_missing(): void
    {
        $user = $this->createVerifiedUser();
        $this->createRestaurantForUser($user, ['slug' => 'validate-bistro']);

        $response = $this->postJson('/api/public/restaurants/validate-bistro/feedback', [
            'rating' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text', 'name']);
    }

    // --- Owner list ---

    public function test_owner_list_feedbacks_returns_200_with_data_newest_first(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $first = $this->createFeedback($restaurant, ['text' => 'First', 'name' => 'A']);
        $second = $this->createFeedback($restaurant, ['text' => 'Second', 'name' => 'B']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [0 => ['uuid', 'rating', 'text', 'name', 'is_approved', 'created_at', 'updated_at']]])
            ->assertJsonMissingPath('data.0.id');
        $data = $response->json('data');
        $uuids = array_column($data, 'uuid');
        $texts = array_column($data, 'text');
        $this->assertContains($first->uuid, $uuids);
        $this->assertContains($second->uuid, $uuids);
        $this->assertContains('First', $texts);
        $this->assertContains('Second', $texts);
        // API contract: newest first (relationship uses orderByDesc('created_at'))
        $this->assertSame($second->uuid, $data[0]['uuid'], 'Newest feedback should be first');
    }

    public function test_owner_list_feedbacks_returns_404_when_restaurant_not_owned(): void
    {
        $owner = $this->createVerifiedUser();
        $other = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($owner);
        $token = $other->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Restaurant not found.');
    }

    public function test_owner_list_feedbacks_returns_404_when_restaurant_uuid_not_found(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;
        $fakeUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->getJson('/api/restaurants/' . $fakeUuid . '/feedbacks', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Restaurant not found.');
    }

    // --- Owner update (approve/reject) ---

    public function test_owner_update_feedback_approve_succeeds(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $feedback = $this->createFeedback($restaurant, ['is_approved' => false]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks/' . $feedback->uuid, [
            'is_approved' => true,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Feedback updated.')
            ->assertJsonPath('data.uuid', $feedback->uuid)
            ->assertJsonPath('data.is_approved', true)
            ->assertJsonMissingPath('data.id');

        $feedback->refresh();
        $this->assertTrue($feedback->is_approved);
    }

    public function test_owner_update_feedback_returns_404_when_feedback_belongs_to_other_restaurant(): void
    {
        $owner1 = $this->createVerifiedUser();
        $owner2 = $this->createVerifiedUser();
        $restaurant1 = $this->createRestaurantForUser($owner1);
        $restaurant2 = $this->createRestaurantForUser($owner2);
        $feedback = $this->createFeedback($restaurant1);
        $token = $owner2->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant2->uuid . '/feedbacks/' . $feedback->uuid, [
            'is_approved' => true,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Feedback not found.');
    }

    public function test_owner_update_feedback_returns_404_when_restaurant_not_owned(): void
    {
        $owner = $this->createVerifiedUser();
        $other = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($owner);
        $feedback = $this->createFeedback($restaurant);
        $token = $other->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks/' . $feedback->uuid, [
            'is_approved' => true,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Restaurant not found.');
    }

    public function test_owner_update_feedback_returns_404_when_feedback_uuid_not_found_for_restaurant(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;
        $fakeFeedbackUuid = '00000000-0000-0000-0000-000000000001';

        $response = $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks/' . $fakeFeedbackUuid, [
            'is_approved' => true,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Feedback not found.');
    }

    // --- Owner delete ---

    public function test_owner_delete_feedback_succeeds(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $feedback = $this->createFeedback($restaurant);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks/' . $feedback->uuid, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('feedbacks', ['id' => $feedback->id]);
    }

    public function test_owner_delete_feedback_returns_404_when_restaurant_not_owned(): void
    {
        $owner = $this->createVerifiedUser();
        $other = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($owner);
        $feedback = $this->createFeedback($restaurant);
        $token = $other->createToken('auth')->plainTextToken;

        $response = $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks/' . $feedback->uuid, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Restaurant not found.');
        $this->assertDatabaseHas('feedbacks', ['id' => $feedback->id]);
    }

    public function test_owner_delete_feedback_returns_404_when_feedback_not_found_for_restaurant(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $token = $user->createToken('auth')->plainTextToken;
        $fakeFeedbackUuid = '00000000-0000-0000-0000-000000000002';

        $response = $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks/' . $fakeFeedbackUuid, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Feedback not found.');
    }

    // --- Public restaurant show: only approved feedbacks, correct shape ---

    public function test_public_restaurant_show_includes_only_approved_feedbacks(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'approved-only-bistro']);
        $approved = $this->createFeedback($restaurant, ['text' => 'Approved', 'name' => 'A', 'is_approved' => true]);
        $this->createFeedback($restaurant, ['text' => 'Pending', 'name' => 'B', 'is_approved' => false]);

        $response = $this->getJson('/api/public/restaurants/approved-only-bistro');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'approved-only-bistro');
        $feedbacks = $response->json('data.feedbacks');
        $this->assertIsArray($feedbacks);
        $this->assertCount(1, $feedbacks);
        $this->assertSame($approved->uuid, $feedbacks[0]['uuid']);
        $this->assertSame('Approved', $feedbacks[0]['text']);
        $this->assertSame('A', $feedbacks[0]['name']);
        $this->assertArrayNotHasKey('id', $feedbacks[0]);
        $this->assertArrayNotHasKey('is_approved', $feedbacks[0]);
    }

    public function test_public_restaurant_show_feedbacks_shape_uuid_rating_text_name_created_at_no_id(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user, ['slug' => 'shape-bistro']);
        $this->createFeedback($restaurant, ['rating' => 5, 'text' => 'Perfect', 'name' => 'Guest', 'is_approved' => true]);

        $response = $this->getJson('/api/public/restaurants/shape-bistro');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'shape-bistro');
        $feedbacks = $response->json('data.feedbacks');
        $this->assertCount(1, $feedbacks);
        $this->assertArrayHasKey('uuid', $feedbacks[0]);
        $this->assertArrayHasKey('rating', $feedbacks[0]);
        $this->assertArrayHasKey('text', $feedbacks[0]);
        $this->assertArrayHasKey('name', $feedbacks[0]);
        $this->assertArrayHasKey('created_at', $feedbacks[0]);
        $this->assertArrayNotHasKey('id', $feedbacks[0]);
        $this->assertArrayNotHasKey('updated_at', $feedbacks[0]);
        $this->assertArrayNotHasKey('is_approved', $feedbacks[0]);
    }

    public function test_feedback_endpoints_require_authentication_for_owner_actions(): void
    {
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $feedback = $this->createFeedback($restaurant);

        $this->getJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks')->assertStatus(401);
        $this->patchJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks/' . $feedback->uuid, ['is_approved' => true])->assertStatus(401);
        $this->deleteJson('/api/restaurants/' . $restaurant->uuid . '/feedbacks/' . $feedback->uuid)->assertStatus(401);
    }
}
