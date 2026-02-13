<?php

namespace Tests\Unit\Auth;

use App\Application\Auth\LogoutUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('auth')]
class LogoutUserTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_deletes_current_access_token(): void
    {
        $token = Mockery::mock();
        $token->shouldReceive('delete')->once();

        $user = Mockery::mock(Authenticatable::class);
        $user->shouldReceive('currentAccessToken')->once()->andReturn($token);

        $useCase = new LogoutUser();
        $useCase->handle($user);

        $this->addToAssertionCount(1); // Mockery verifies currentAccessToken()->delete() was called
    }
}
