<?php

namespace Tests\Feature;

use App\Models\OwnerFeedback;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OwnerFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): string
    {
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertOk();

        return $response->json('token');
    }

    public function test_owner_can_submit_feedback_without_restaurant(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $this->login($user);

        $response = $this->postJson('/api/owner-feedback', [
            'message' => 'I need a dark mode for the menu.',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Feedback submitted.')
            ->assertJsonPath('data.message', 'I need a dark mode for the menu.')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.restaurant', null)
            ->assertJsonStructure(['data' => ['uuid', 'created_at', 'submitter' => ['uuid', 'name']]])
            ->assertJsonMissingPath('data.id');

        $this->assertDatabaseHas('owner_feedbacks', [
            'user_id' => $user->id,
            'message' => 'I need a dark mode for the menu.',
            'restaurant_id' => null,
            'status' => 'pending',
        ]);
    }

    private function createRestaurantForUser(User $user): Restaurant
    {
        $r = new Restaurant;
        $r->user_id = $user->id;
        $r->name = 'Test Restaurant';
        $r->slug = 'test-restaurant-' . uniqid();
        $r->default_locale = 'en';
        $r->save();
        $r->languages()->create(['locale' => $r->default_locale]);

        return $r;
    }

    public function test_owner_can_submit_feedback_with_title_and_restaurant(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantForUser($user);
        $token = $this->login($user);

        $response = $this->postJson('/api/owner-feedback', [
            'message' => 'QR code for table ordering.',
            'title' => 'Table ordering',
            'restaurant' => $restaurant->uuid,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Table ordering')
            ->assertJsonPath('data.restaurant.uuid', $restaurant->uuid)
            ->assertJsonPath('data.restaurant.name', $restaurant->name);

        $this->assertDatabaseHas('owner_feedbacks', [
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'title' => 'Table ordering',
        ]);
    }

    public function test_owner_submit_with_restaurant_not_owned_returns_403(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email' => 'other@example.com', 'email_verified_at' => now()]);
        $restaurant = $this->createRestaurantForUser($otherUser);
        $token = $this->login($owner);

        $response = $this->postJson('/api/owner-feedback', [
            'message' => 'Feature request.',
            'restaurant' => $restaurant->uuid,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertForbidden()
            ->assertJsonFragment(['message' => 'You do not have access to that restaurant.']);
        $this->assertDatabaseMissing('owner_feedbacks', ['user_id' => $owner->id]);
    }

    public function test_owner_submit_validation_422_for_missing_message(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $this->login($user);

        $response = $this->postJson('/api/owner-feedback', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);
    }

    public function test_owner_list_returns_only_own_submissions_newest_first(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email' => 'other@example.com', 'email_verified_at' => now()]);
        $first = OwnerFeedback::create([
            'user_id' => $user->id,
            'message' => 'First',
            'status' => 'pending',
        ]);
        $second = OwnerFeedback::create([
            'user_id' => $user->id,
            'message' => 'Second',
            'status' => 'pending',
        ]);
        OwnerFeedback::create([
            'user_id' => $other->id,
            'message' => 'Other user',
            'status' => 'pending',
        ]);

        $token = $this->login($user);
        $response = $this->getJson('/api/owner-feedback', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.message', 'Second')
            ->assertJsonPath('data.1.message', 'First');
        $uuids = $response->json('data.*.uuid');
        $this->assertContains($first->uuid, $uuids);
        $this->assertContains($second->uuid, $uuids);
        $response->assertJsonMissingPath('data.0.id');
    }

    public function test_superadmin_list_all_owner_feedbacks(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@admin.com',
            'email_verified_at' => now(),
            'is_superadmin' => true,
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $feedback = OwnerFeedback::create([
            'user_id' => $owner->id,
            'message' => 'Superadmin sees this',
            'status' => 'pending',
        ]);

        $token = $this->login($admin);
        $response = $this->getJson('/api/superadmin/owner-feedbacks', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $feedback->uuid)
            ->assertJsonPath('data.0.submitter.uuid', $owner->uuid)
            ->assertJsonPath('data.0.submitter.email', $owner->email)
            ->assertJsonPath('data.0.restaurant', null)
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_superadmin_patch_feedback_status(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@admin.com',
            'email_verified_at' => now(),
            'is_superadmin' => true,
        ]);
        $feedback = OwnerFeedback::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'message' => 'Done feature',
            'status' => 'pending',
        ]);
        $token = $this->login($admin);

        $response = $this->patchJson('/api/superadmin/owner-feedbacks/' . $feedback->uuid, [
            'status' => 'reviewed',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Feedback updated.')
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.uuid', $feedback->uuid)
            ->assertJsonMissingPath('data.id');

        $feedback->refresh();
        $this->assertSame('reviewed', $feedback->status);
    }

    public function test_superadmin_patch_feedback_returns_422_for_invalid_status(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@admin.com',
            'email_verified_at' => now(),
            'is_superadmin' => true,
        ]);
        $feedback = OwnerFeedback::create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'message' => 'Some feedback',
            'status' => 'pending',
        ]);
        $token = $this->login($admin);

        $response = $this->patchJson('/api/superadmin/owner-feedbacks/' . $feedback->uuid, [
            'status' => 'invalid_status',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
        $feedback->refresh();
        $this->assertSame('pending', $feedback->status);
    }

    public function test_superadmin_list_owner_feedbacks_newest_first(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@admin.com',
            'email_verified_at' => now(),
            'is_superadmin' => true,
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $older = OwnerFeedback::create([
            'user_id' => $owner->id,
            'message' => 'Older feedback',
            'status' => 'pending',
        ]);
        $newer = OwnerFeedback::create([
            'user_id' => $owner->id,
            'message' => 'Newer feedback',
            'status' => 'pending',
        ]);
        $past = now()->subMinutes(10);
        $recent = now()->subMinutes(5);
        DB::table('owner_feedbacks')->where('id', $older->getKey())->update(['created_at' => $past]);
        DB::table('owner_feedbacks')->where('id', $newer->getKey())->update(['created_at' => $recent]);

        $token = $this->login($admin);
        $response = $this->getJson('/api/superadmin/owner-feedbacks', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.uuid', $newer->uuid)
            ->assertJsonPath('data.0.message', 'Newer feedback')
            ->assertJsonPath('data.1.uuid', $older->uuid)
            ->assertJsonPath('data.1.message', 'Older feedback');
    }

    public function test_superadmin_patch_feedback_404_for_unknown_uuid(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@admin.com',
            'email_verified_at' => now(),
            'is_superadmin' => true,
        ]);
        $token = $this->login($admin);

        $response = $this->patchJson('/api/superadmin/owner-feedbacks/00000000-0000-0000-0000-000000000000', [
            'status' => 'reviewed',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertNotFound()
            ->assertJson(['message' => 'Feedback not found.']);
    }

    public function test_regular_user_superadmin_owner_feedbacks_returns_403(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_superadmin' => false]);
        $token = $this->login($user);

        $response = $this->getJson('/api/superadmin/owner-feedbacks', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertForbidden()
            ->assertJson(['message' => 'Forbidden.']);
    }
}
