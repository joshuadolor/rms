<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Asserts that the expected notifications (emails) are sent when users
 * register, request password reset, or resend verification.
 */
#[Group('auth')]
class EmailSentTest extends TestCase
{
    public function test_register_sends_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_forgot_password_sends_reset_email_when_user_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'resetme@example.com']);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'resetme@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If that email exists in our system, we have sent a password reset link.');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_resend_verification_sends_verification_email_when_user_unverified(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'resend@example.com']);

        $response = $this->postJson('/api/email/resend', [
            'email' => 'resend@example.com',
        ]);

        $response->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
