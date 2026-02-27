<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Tests\TestCase;

class RefreshTokenServiceTest extends TestCase
{
    public function test_issue_and_rotate_refresh_token(): void
    {
        $user = User::factory()->create();

        $service = app(RefreshTokenService::class);

        $plain1 = $service->issueForUser($user);
        $this->assertNotEmpty($plain1);

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain1),
        ]);

        $result = $service->rotateAndIssueAccessToken($plain1);

        $this->assertSame($user->id, $result['user']->id);
        $this->assertNotEmpty($result['token']);
        $this->assertNotEmpty($result['refresh_token']);
        $this->assertNotSame($plain1, $result['refresh_token']);

        $old = RefreshToken::query()->where('token_hash', hash('sha256', $plain1))->firstOrFail();
        $new = RefreshToken::query()->where('token_hash', hash('sha256', $result['refresh_token']))->firstOrFail();

        $this->assertNotNull($old->revoked_at);
        $this->assertNull($new->revoked_at);
        $this->assertSame($old->id, $new->rotated_from_id);
    }

    public function test_revoked_token_within_rotation_grace_can_be_used_again(): void
    {
        config()->set('refresh_tokens.rotation_grace_seconds', 30);

        $user = User::factory()->create();
        $service = app(RefreshTokenService::class);

        $plain1 = $service->issueForUser($user);
        $result1 = $service->rotateAndIssueAccessToken($plain1);
        $plain2 = $result1['refresh_token'];

        // Simulate a near-parallel refresh: reuse the original (now revoked) token within grace.
        $result2 = $service->rotateAndIssueAccessToken($plain1);
        $plain3 = $result2['refresh_token'];

        $this->assertNotEmpty($plain2);
        $this->assertNotEmpty($plain3);
        $this->assertNotSame($plain1, $plain2);
        $this->assertNotSame($plain1, $plain3);
        $this->assertNotSame($plain2, $plain3);

        $token1 = RefreshToken::query()->where('token_hash', hash('sha256', $plain1))->firstOrFail();
        $token2 = RefreshToken::query()->where('token_hash', hash('sha256', $plain2))->firstOrFail();
        $token3 = RefreshToken::query()->where('token_hash', hash('sha256', $plain3))->firstOrFail();

        $this->assertSame($token2->id, $token1->rotated_to_id);
        $this->assertNotNull($token1->revoked_at);

        $this->assertSame($token3->id, $token2->rotated_to_id);
        $this->assertNotNull($token2->revoked_at);

        $this->assertNull($token3->revoked_at);
    }
}

