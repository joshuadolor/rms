<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
class EmailVerificationTest extends TestCase
{
    public function test_register_returns_201_with_user_and_message_without_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Registered. Please verify your email using the link we sent you.')
            ->assertJsonStructure(['message', 'user' => ['id', 'name', 'email', 'email_verified_at']])
            ->assertJsonMissing(['token', 'token_type']);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->assertNull($response->json('user.email_verified_at'));
    }

    public function test_verify_email_marks_user_verified(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'verify@example.com']);

        $url = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('message', 'Email verified successfully. You can now log in.')
            ->assertJsonPath('user.email_verified_at', fn ($v) => $v !== null);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_email_already_verified_returns_ok(): void
    {
        $user = User::factory()->create(['email' => 'already@example.com']);

        $url = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('message', 'Email already verified.');
    }

    public function test_resend_as_guest_returns_generic_message(): void
    {
        User::factory()->unverified()->create(['email' => 'resend@example.com']);

        $response = $this->postJson('/api/email/resend', [
            'email' => 'resend@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If that email exists and is unverified, we have sent a new verification link.');
    }

    public function test_resend_as_guest_with_unknown_email_returns_same_generic_message(): void
    {
        $response = $this->postJson('/api/email/resend', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If that email exists and is unverified, we have sent a new verification link.');
    }

    public function test_protected_route_returns_403_with_json_message_when_email_not_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/user', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(403)
            ->assertExactJson(['message' => 'Your email address is not verified.']);
    }

    public function test_protected_route_allows_verified_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/user', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'email_verified_at']]);
    }
}
