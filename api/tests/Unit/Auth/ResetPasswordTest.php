<?php

namespace Tests\Unit\Auth;

use App\Application\Auth\ResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
class ResetPasswordTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_returns_success_message_when_reset_succeeds(): void
    {
        Password::shouldReceive('reset')
            ->once()
            ->with(
                Mockery::on(fn (array $input) => $input['email'] === 'user@example.com' && $input['token'] === 'token'),
                Mockery::type('callable')
            )
            ->andReturn(Password::PASSWORD_RESET);

        $useCase = new ResetPassword();
        $message = $useCase->handle([
            'token' => 'token',
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $this->assertSame('Your password has been reset.', $message);
    }

    public function test_handle_throws_when_token_invalid_or_expired(): void
    {
        Password::shouldReceive('reset')
            ->once()
            ->andReturn(Password::INVALID_TOKEN);

        $useCase = new ResetPassword();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This password reset token is invalid or has expired');

        $useCase->handle([
            'token' => 'bad-token',
            'email' => 'user@example.com',
            'password' => 'newpass',
            'password_confirmation' => 'newpass',
        ]);
    }
}
