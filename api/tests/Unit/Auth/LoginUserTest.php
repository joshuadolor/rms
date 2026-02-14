<?php

namespace Tests\Unit\Auth;

use App\Application\Auth\LoginUser;
use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Exceptions\UnverifiedEmailException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
class LoginUserTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_returns_user_and_token_when_credentials_valid_and_email_verified(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->email = 'test@example.com';
        $user->password = Hash::make('password123');
        $user->shouldReceive('hasVerifiedEmail')->once()->andReturn(true);
        $user->shouldReceive('createToken')->once()->with('auth')->andReturn(
            (object) ['plainTextToken' => 'login-token-456']
        );

        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('findByEmail')->once()->with('test@example.com')->andReturn($user);

        $useCase = new LoginUser($repo);
        $result = $useCase->handle([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame($user, $result['user']);
        $this->assertSame('login-token-456', $result['token']);
    }

    public function test_handle_throws_unverified_email_exception_when_email_not_verified(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->email = 'unverified@example.com';
        $user->password = Hash::make('password123');
        $user->shouldReceive('hasVerifiedEmail')->once()->andReturn(false);
        $user->shouldNotReceive('createToken');

        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('findByEmail')->once()->with('unverified@example.com')->andReturn($user);

        $useCase = new LoginUser($repo);

        $this->expectException(UnverifiedEmailException::class);
        $this->expectExceptionMessage('Your email address is not verified.');

        $useCase->handle([
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_handle_throws_when_user_not_found(): void
    {
        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('findByEmail')->once()->with('missing@example.com')->andReturn(null);

        $useCase = new LoginUser($repo);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect');

        $useCase->handle([
            'email' => 'missing@example.com',
            'password' => 'any',
        ]);
    }

    public function test_handle_throws_when_password_wrong(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->password = Hash::make('correct');

        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('findByEmail')->once()->with('test@example.com')->andReturn($user);

        $useCase = new LoginUser($repo);

        $this->expectException(ValidationException::class);

        $useCase->handle([
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]);
    }
}
