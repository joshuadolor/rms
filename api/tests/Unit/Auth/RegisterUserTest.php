<?php

namespace Tests\Unit\Auth;

use App\Application\Auth\RegisterUser;
use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('auth')]
class RegisterUserTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_creates_user_sends_verification_and_returns_user_without_token(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->name = 'Test';
        $user->email = 'test@example.com';
        $user->shouldReceive('sendEmailVerificationNotification')->once();
        $user->shouldNotReceive('createToken');

        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('create')->once()->with(Mockery::on(function (array $data) {
            return $data['name'] === 'Test'
                && $data['email'] === 'test@example.com'
                && $data['password'] === 'password123';
        }))->andReturn($user);

        $useCase = new RegisterUser($repo);
        $result = $useCase->handle([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame($user, $result['user']);
        $this->assertArrayNotHasKey('token', $result);
    }
}
