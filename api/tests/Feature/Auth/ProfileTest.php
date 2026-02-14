<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyNewEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
class ProfileTest extends TestCase
{
    public function test_update_profile_name_only_updates_immediately(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'user@example.com']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/user', [
            'name' => 'New Name',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('user.name', 'New Name')
            ->assertJsonPath('user.email', 'user@example.com');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
    }

    public function test_update_profile_email_unchanged_returns_profile_updated(): void
    {
        $user = User::factory()->create(['email' => 'same@example.com']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/user', [
            'email' => 'same@example.com',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Profile updated.');
        $this->assertNull($user->refresh()->pending_email);
    }

    public function test_update_profile_new_email_sets_pending_and_sends_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'current@example.com']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/user', [
            'email' => 'newemail@example.com',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'A verification link has been sent to your new email address. Please confirm to complete the change.')
            ->assertJsonPath('user.email', 'current@example.com')
            ->assertJsonPath('user.pending_email', 'newemail@example.com');

        $user->refresh();
        $this->assertSame('current@example.com', $user->email);
        $this->assertSame('newemail@example.com', $user->pending_email);

        Notification::assertSentTo($user, VerifyNewEmailNotification::class);
    }

    public function test_update_profile_requires_authentication(): void
    {
        $response = $this->patchJson('/api/user', [
            'name' => 'Any',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_profile_returns_403_when_email_not_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/user', [
            'name' => 'New Name',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(403)
            ->assertExactJson(['message' => 'Your email address is not verified.']);
    }

    public function test_update_profile_email_validation_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'me@example.com']);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/user', [
            'email' => 'taken@example.com',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_change_password_succeeds_and_updates_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpass'),
        ]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/profile/password', [
            'current_password' => 'oldpass',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Password updated successfully.');

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/profile/password', [
            'current_password' => 'wrong',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_change_password_validation_requires_confirmation(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'mismatch',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_verify_new_email_succeeds_and_updates_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);

        $url = URL::temporarySignedRoute(
            'api.verification.verify-new',
            now()->addMinutes(60),
            ['uuid' => $user->uuid, 'hash' => sha1('new@example.com')]
        );

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('message', 'Your email has been updated and verified.')
            ->assertJsonPath('user.email', 'new@example.com');

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_new_email_invalid_hash_returns_422(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);

        $url = URL::temporarySignedRoute(
            'api.verification.verify-new',
            now()->addMinutes(60),
            ['uuid' => $user->uuid, 'hash' => 'wrong-hash']
        );

        $response = $this->getJson($url);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
        $this->assertSame('old@example.com', $user->refresh()->email);
    }

    public function test_verify_new_email_no_pending_returns_422(): void
    {
        $user = User::factory()->create([
            'email' => 'only@example.com',
            'pending_email' => null,
        ]);

        $url = URL::temporarySignedRoute(
            'api.verification.verify-new',
            now()->addMinutes(60),
            ['uuid' => $user->uuid, 'hash' => sha1('only@example.com')]
        );

        $response = $this->getJson($url);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
