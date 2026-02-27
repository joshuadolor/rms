<?php

namespace Tests\Feature\Auth;

use App\Exceptions\DeactivatedUserException;
use App\Exceptions\UnverifiedEmailException;
use App\Models\User;
use App\Services\Auth\RefreshTokenCookie;
use App\Services\Auth\RefreshTokenService;
use Tests\TestCase;

class RefreshEndpointTest extends TestCase
{
    public function test_refresh_401_clears_refresh_cookie_when_missing(): void
    {
        $cookie = app(RefreshTokenCookie::class);

        $response = $this->postJson('/api/auth/refresh');

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid or expired refresh token.',
        ]);

        $setCookies = $response->headers->getCookies();
        $refresh = collect($setCookies)->first(fn ($c) => $c->getName() === $cookie->name());

        $this->assertNotNull($refresh, 'Expected refresh cookie to be cleared on 401.');
        $this->assertSame('', $refresh->getValue());
        $this->assertTrue($refresh->getExpiresTime() < time(), 'Expected cleared refresh cookie to be expired.');
        $this->assertTrue($refresh->isHttpOnly(), 'Expected refresh cookie to remain HttpOnly.');
    }

    public function test_refresh_403_unverified_clears_refresh_cookie(): void
    {
        $cookie = app(RefreshTokenCookie::class);

        $user = User::factory()->unverified()->create();
        $plain = app(RefreshTokenService::class)->issueForUser($user);

        $response = $this
            ->withUnencryptedCookie($cookie->name(), $plain)
            ->withCredentials()
            ->postJson('/api/auth/refresh');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => UnverifiedEmailException::MESSAGE,
        ]);

        $setCookies = $response->headers->getCookies();
        $refresh = collect($setCookies)->first(fn ($c) => $c->getName() === $cookie->name());

        $this->assertNotNull($refresh, 'Expected refresh cookie to be cleared on 403 (unverified).');
        $this->assertSame('', $refresh->getValue());
        $this->assertTrue($refresh->getExpiresTime() < time(), 'Expected cleared refresh cookie to be expired.');
    }

    public function test_refresh_403_deactivated_clears_refresh_cookie(): void
    {
        $cookie = app(RefreshTokenCookie::class);

        $user = User::factory()->create(['is_active' => false]);
        $plain = app(RefreshTokenService::class)->issueForUser($user);

        $response = $this
            ->withUnencryptedCookie($cookie->name(), $plain)
            ->withCredentials()
            ->postJson('/api/auth/refresh');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => DeactivatedUserException::MESSAGE,
        ]);

        $setCookies = $response->headers->getCookies();
        $refresh = collect($setCookies)->first(fn ($c) => $c->getName() === $cookie->name());

        $this->assertNotNull($refresh, 'Expected refresh cookie to be cleared on 403 (deactivated).');
        $this->assertSame('', $refresh->getValue());
        $this->assertTrue($refresh->getExpiresTime() < time(), 'Expected cleared refresh cookie to be expired.');
    }
}

