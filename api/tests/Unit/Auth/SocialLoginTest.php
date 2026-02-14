<?php

namespace Tests\Unit\Auth;

use App\Application\Auth\SocialLogin;
use App\Domain\Auth\Contracts\SocialAccountRepositoryInterface;
use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Models\SocialAccount;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
class SocialLoginTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function userWithToken(string $tokenValue): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->email = 'sso@example.com';
        $user->name = 'SSO User';
        $user->shouldReceive('createToken')->with('auth')->andReturn(
            (object) ['plainTextToken' => $tokenValue]
        );

        return $user;
    }

    public function test_handle_returns_existing_user_and_token_when_social_account_exists(): void
    {
        $user = $this->userWithToken('existing-token');
        $social = new SocialAccount;
        $social->setRelation('user', $user);

        $socialRepo = Mockery::mock(SocialAccountRepositoryInterface::class);
        $socialRepo->shouldReceive('findByProviderAndProviderId')->once()
            ->with('google', 'google-123')->andReturn($social);
        $socialRepo->shouldNotReceive('createForUser');

        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldNotReceive('findByEmail');
        $userRepo->shouldNotReceive('create');

        $useCase = new SocialLogin($userRepo, $socialRepo);
        $result = $useCase->handle([
            'provider' => 'google',
            'provider_id' => 'google-123',
            'email' => 'sso@example.com',
            'name' => 'SSO User',
        ]);

        $this->assertSame($user, $result['user']);
        $this->assertSame('existing-token', $result['token']);
    }

    public function test_handle_creates_user_and_social_account_when_new(): void
    {
        $user = $this->userWithToken('new-token');

        $socialRepo = Mockery::mock(SocialAccountRepositoryInterface::class);
        $socialRepo->shouldReceive('findByProviderAndProviderId')->once()
            ->with('google', 'google-456')->andReturn(null);
        $socialRepo->shouldReceive('createForUser')->once()->with($user, 'google', 'google-456');

        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldReceive('findByEmail')->once()->with('new@example.com')->andReturn(null);
        $userRepo->shouldReceive('create')->once()->with(Mockery::on(function (array $data) {
            return $data['name'] === 'New User'
                && $data['email'] === 'new@example.com'
                && isset($data['password'])
                && ! array_key_exists('email_verified_at', $data); // set after create via forceFill (security)
        }))->andReturn($user);
        $user->shouldReceive('forceFill')->once()->with(Mockery::on(function (array $data) {
            return array_key_exists('email_verified_at', $data);
        }))->andReturnSelf();
        $user->shouldReceive('save')->once();

        $useCase = new SocialLogin($userRepo, $socialRepo);
        $result = $useCase->handle([
            'provider' => 'google',
            'provider_id' => 'google-456',
            'email' => 'new@example.com',
            'name' => 'New User',
        ]);

        $this->assertSame($user, $result['user']);
        $this->assertSame('new-token', $result['token']);
    }

    public function test_handle_links_existing_user_by_email_and_creates_social_account(): void
    {
        $user = $this->userWithToken('linked-token');

        $socialRepo = Mockery::mock(SocialAccountRepositoryInterface::class);
        $socialRepo->shouldReceive('findByProviderAndProviderId')->once()
            ->with('facebook', 'fb-789')->andReturn(null);
        $socialRepo->shouldReceive('createForUser')->once()->with($user, 'facebook', 'fb-789');

        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldReceive('findByEmail')->once()->with('existing@example.com')->andReturn($user);
        $userRepo->shouldNotReceive('create');

        $useCase = new SocialLogin($userRepo, $socialRepo);
        $result = $useCase->handle([
            'provider' => 'facebook',
            'provider_id' => 'fb-789',
            'email' => 'existing@example.com',
            'name' => 'Existing',
        ]);

        $this->assertSame($user, $result['user']);
        $this->assertSame('linked-token', $result['token']);
    }

    public function test_handle_uses_placeholder_email_when_email_null(): void
    {
        $user = $this->userWithToken('ig-token');

        $socialRepo = Mockery::mock(SocialAccountRepositoryInterface::class);
        $socialRepo->shouldReceive('findByProviderAndProviderId')->once()
            ->with('instagram', 'ig-999')->andReturn(null);
        $socialRepo->shouldReceive('createForUser')->once()->with($user, 'instagram', 'ig-999');

        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldNotReceive('findByEmail'); // not called when email is null
        $userRepo->shouldReceive('create')->once()->with(Mockery::on(function (array $data) {
            return $data['email'] === 'instagram_ig-999@placeholder.rms.local'
                && $data['name'] === 'iguser'
                && ! array_key_exists('email_verified_at', $data); // no email from provider
        }))->andReturn($user);

        $useCase = new SocialLogin($userRepo, $socialRepo);
        $result = $useCase->handle([
            'provider' => 'instagram',
            'provider_id' => 'ig-999',
            'email' => null,
            'name' => 'iguser',
        ]);

        $this->assertSame('ig-token', $result['token']);
    }
}
