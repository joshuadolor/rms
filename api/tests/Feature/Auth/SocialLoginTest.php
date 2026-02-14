<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('auth')]
class SocialLoginTest extends TestCase
{
    protected function createSocialiteUser(string $id, ?string $email, string $name, ?string $nickname = null): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->id = $id;
        $user->email = $email;
        $user->name = $name;
        $user->nickname = $nickname;

        return $user;
    }

    public function test_google_sso_creates_user_and_returns_token(): void
    {
        $socialUser = $this->createSocialiteUser('google-123', 'sso@example.com', 'SSO User');

        $driver = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $driver->shouldReceive('userFromToken')->with('fake-google-token')->once()->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $response = $this->postJson('/api/auth/google', [
            'access_token' => 'fake-google-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user' => ['uuid', 'name', 'email'], 'token', 'token_type'])
            ->assertJson(['message' => 'Logged in successfully.', 'token_type' => 'Bearer']);

        $this->assertDatabaseHas('users', ['email' => 'sso@example.com', 'name' => 'SSO User']);
        $this->assertDatabaseHas('social_accounts', ['provider' => 'google', 'provider_id' => 'google-123']);
    }

    public function test_google_sso_returns_401_on_invalid_token(): void
    {
        $driver = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $driver->shouldReceive('userFromToken')->with('invalid-token')->once()->andThrow(new \Exception('Invalid token'));

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $response = $this->postJson('/api/auth/google', [
            'access_token' => 'invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid or expired Google token.']);
    }

    public function test_facebook_sso_creates_user_and_returns_token(): void
    {
        $socialUser = $this->createSocialiteUser('fb-456', 'fb@example.com', 'FB User', 'fbnick');

        $driver = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $driver->shouldReceive('userFromToken')->with('fake-fb-token')->once()->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('facebook')->once()->andReturn($driver);

        $response = $this->postJson('/api/auth/facebook', [
            'access_token' => 'fake-fb-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['uuid', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'fb@example.com']);
        $this->assertDatabaseHas('social_accounts', ['provider' => 'facebook', 'provider_id' => 'fb-456']);
    }

    public function test_instagram_sso_creates_user_and_returns_token(): void
    {
        Http::fake([
            'https://graph.instagram.com/me*' => Http::response(['id' => 'ig-789', 'username' => 'iguser'], 200),
        ]);

        $response = $this->postJson('/api/auth/instagram', [
            'access_token' => 'fake-ig-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['uuid', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'instagram_ig-789@placeholder.rms.local', 'name' => 'iguser']);
        $this->assertDatabaseHas('social_accounts', ['provider' => 'instagram', 'provider_id' => 'ig-789']);
    }

    public function test_instagram_sso_returns_401_on_invalid_token(): void
    {
        Http::fake([
            'https://graph.instagram.com/me*' => Http::response([], 401),
        ]);

        $response = $this->postJson('/api/auth/instagram', [
            'access_token' => 'invalid-ig-token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid or expired Instagram token.']);
    }

    public function test_sso_existing_linked_user_returns_same_user_and_new_token(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com', 'name' => 'Existing']);
        $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'google-existing']);

        $socialUser = $this->createSocialiteUser('google-existing', 'existing@example.com', 'Existing');

        $driver = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $driver->shouldReceive('userFromToken')->with('token')->once()->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $response = $this->postJson('/api/auth/google', ['access_token' => 'token']);

        $response->assertOk();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 1);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
