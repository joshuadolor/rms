<?php

namespace Tests\Unit\Auth;

use App\Application\Auth\ForgotPassword;
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

    public function test_handle_always_returns_generic_message_regardless_of_broker_status(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'user@example.com'])
            ->andReturn(Password::RESET_LINK_SENT);

        $useCase = new ForgotPassword();
        $message = $useCase->handle(['email' => 'user@example.com']);

        $this->assertSame('If that email exists in our system, we have sent a password reset link.', $message);
    }

    public function test_handle_returns_same_message_when_broker_returns_invalid_user(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'missing@example.com'])
            ->andReturn(Password::INVALID_USER);

        $useCase = new ForgotPassword();
        $message = $useCase->handle(['email' => 'missing@example.com']);

        $this->assertSame('If that email exists in our system, we have sent a password reset link.', $message);
    }
}
