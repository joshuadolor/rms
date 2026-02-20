<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('superadmin')]
class SuperadminTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperadmin(): User
    {
        return User::factory()->create([
            'email' => 'admin@admin.com',
            'email_verified_at' => now(),
            'is_superadmin' => true,
            'is_active' => true,
        ]);
    }

    private function login(User $user): string
    {
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertOk();

        return $response->json('token');
    }

    public function test_superadmin_stats_returns_200_with_counts(): void
    {
        $admin = $this->createSuperadmin();
        $token = $this->login($admin);

        $response = $this->getJson('/api/superadmin/stats', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'restaurants_count',
                    'users_count',
                    'paid_users_count',
                ],
            ]);
        $this->assertIsInt($response->json('data.restaurants_count'));
        $this->assertIsInt($response->json('data.users_count'));
        $this->assertIsInt($response->json('data.paid_users_count'));
    }

    public function test_superadmin_users_returns_200_with_user_payloads(): void
    {
        $admin = $this->createSuperadmin();
        $token = $this->login($admin);

        $response = $this->getJson('/api/superadmin/users', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => [['uuid', 'name', 'email', 'email_verified_at', 'is_paid', 'is_active', 'is_superadmin']]])
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_superadmin_restaurants_returns_200_with_restaurant_payloads(): void
    {
        $admin = $this->createSuperadmin();
        $token = $this->login($admin);

        $response = $this->getJson('/api/superadmin/restaurants', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()->assertJsonStructure(['data']);
        $this->assertArrayNotHasKey('id', $response->json('data.0') ?? []);
    }

    public function test_regular_user_superadmin_stats_returns_403(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_superadmin' => false,
        ]);
        $token = $this->login($user);

        $response = $this->getJson('/api/superadmin/stats', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertForbidden()
            ->assertJson(['message' => 'Forbidden.']);
    }

    public function test_superadmin_patch_user_can_set_is_paid_and_is_active(): void
    {
        $admin = $this->createSuperadmin();
        $other = User::factory()->create([
            'email' => 'other@example.com',
            'email_verified_at' => now(),
            'is_paid' => false,
            'is_active' => true,
        ]);
        $token = $this->login($admin);

        $response = $this->patchJson('/api/superadmin/users/' . $other->uuid, [
            'is_paid' => true,
            'is_active' => false,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.uuid', $other->uuid);

        $other->refresh();
        $this->assertTrue($other->is_paid);
        $this->assertFalse($other->is_active);
    }

    public function test_superadmin_cannot_change_own_is_active(): void
    {
        $admin = $this->createSuperadmin();
        $token = $this->login($admin);

        $response = $this->patchJson('/api/superadmin/users/' . $admin->uuid, [
            'is_active' => false,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422);

        $admin->refresh();
        $this->assertTrue($admin->is_active);
    }

    public function test_login_deactivated_user_returns_403(): void
    {
        $user = User::factory()->create([
            'email' => 'deactivated@example.com',
            'email_verified_at' => now(),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'deactivated@example.com',
            'password' => 'password',
        ]);

        $response->assertForbidden()
            ->assertJson(['message' => 'Your account has been deactivated.']);
    }

    public function test_superadmin_patch_user_returns_404_for_nonexistent_user_uuid(): void
    {
        $admin = $this->createSuperadmin();
        $token = $this->login($admin);
        $nonExistentUuid = (string) Str::uuid();

        $response = $this->patchJson('/api/superadmin/users/' . $nonExistentUuid, [
            'is_active' => false,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertNotFound()
            ->assertJson(['message' => 'User not found.']);
    }

    public function test_superadmin_users_list_includes_is_active_per_user(): void
    {
        $admin = $this->createSuperadmin();
        $activeUser = User::factory()->create([
            'email' => 'active@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $inactiveUser = User::factory()->create([
            'email' => 'inactive@example.com',
            'email_verified_at' => now(),
            'is_active' => false,
        ]);
        $token = $this->login($admin);

        $response = $this->getJson('/api/superadmin/users', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        foreach ($data as $row) {
            $this->assertArrayNotHasKey('id', $row, 'User payload must not contain internal id');
            $this->assertArrayHasKey('uuid', $row);
            $this->assertArrayHasKey('is_active', $row);
        }
        $activeRow = collect($data)->firstWhere('uuid', $activeUser->uuid);
        $inactiveRow = collect($data)->firstWhere('uuid', $inactiveUser->uuid);
        $this->assertNotNull($activeRow, 'Active user should appear in list');
        $this->assertNotNull($inactiveRow, 'Inactive user should appear in list');
        $this->assertTrue($activeRow['is_active']);
        $this->assertFalse($inactiveRow['is_active']);
    }

    public function test_superadmin_patch_user_validation_rejects_invalid_body(): void
    {
        $admin = $this->createSuperadmin();
        $other = User::factory()->create([
            'email' => 'other@example.com',
            'email_verified_at' => now(),
        ]);
        $token = $this->login($admin);

        $response = $this->patchJson('/api/superadmin/users/' . $other->uuid, [
            'is_paid' => 'not-a-boolean',
            'is_active' => 'invalid',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_paid', 'is_active']);
    }

    public function test_superadmin_restaurants_returns_payload_shape_and_no_internal_id(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = new Restaurant;
        $restaurant->user_id = $owner->id;
        $restaurant->name = 'Superadmin List Restaurant';
        $restaurant->slug = 'superadmin-list-' . Str::random(8);
        $restaurant->default_locale = 'en';
        $restaurant->save();
        $restaurant->languages()->create(['locale' => $restaurant->default_locale]);

        $admin = $this->createSuperadmin();
        $token = $this->login($admin);

        $response = $this->getJson('/api/superadmin/restaurants', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    0 => [
                        'uuid',
                        'name',
                        'tagline',
                        'slug',
                        'public_url',
                        'default_locale',
                        'currency',
                        'languages',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonPath('data.0.uuid', $restaurant->uuid)
            ->assertJsonPath('data.0.name', $restaurant->name)
            ->assertJsonMissingPath('data.0.id');
    }
}
