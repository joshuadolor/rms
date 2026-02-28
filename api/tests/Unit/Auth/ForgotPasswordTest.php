<?php

namespace Tests\Unit\Auth;

use App\Application\Auth\ForgotPassword;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Password;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
class ForgotPasswordTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_returns_success_when_reset_link_sent(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'user@example.com'])
            ->andReturn(Password::RESET_LINK_SENT);

        $useCase = new ForgotPassword();
        $result = $useCase->handle(['email' => 'user@example.com']);

        $this->assertTrue($result['success']);
        $this->assertSame('If that email exists in our system, we have sent a password reset link.', $result['message']);
    }

    public function test_handle_returns_success_with_generic_message_when_broker_returns_invalid_user(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'missing@example.com'])
            ->andReturn(Password::INVALID_USER);

        $useCase = new ForgotPassword();
        $result = $useCase->handle(['email' => 'missing@example.com']);

        $this->assertTrue($result['success']);
        $this->assertSame('If that email exists in our system, we have sent a password reset link.', $result['message']);
    }

    public function test_handle_returns_failure_when_send_throws(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'user@example.com'])
            ->andThrow(new \RuntimeException('SMTP failed'));

        $useCase = new ForgotPassword();
        $result = $useCase->handle(['email' => 'user@example.com']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('try again', $result['message']);
    }

    public function test_handle_returns_failure_when_app_key_missing(): void
    {
        $originalKey = config('app.key');
        Config::set('app.key', null);

        try {
            $useCase = new ForgotPassword();
            $result = $useCase->handle(['email' => 'user@example.com']);

            $this->assertFalse($result['success']);
            $this->assertStringContainsString('try again', $result['message']);
        } finally {
            Config::set('app.key', $originalKey);
        }
    }
}
