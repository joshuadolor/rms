<?php

namespace App\Services\Auth;

use App\Exceptions\DeactivatedUserException;
use App\Exceptions\InvalidRefreshTokenException;
use App\Exceptions\UnverifiedEmailException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class RefreshTokenService
{
    /**
     * @return array{user: User, token: string, refresh_token: string}
     */
    public function rotateAndIssueAccessToken(string $plainRefreshToken): array
    {
        return DB::transaction(function () use ($plainRefreshToken): array {
            $existing = $this->findByPlainTokenForUpdate($plainRefreshToken);

            /** @var RefreshToken|null $existing */
            if (! $existing) {
                throw new InvalidRefreshTokenException();
            }

            $token = $this->resolveTokenForRotation($existing);

            $user = $token->user()->first();
            if (! $user) {
                // Defensive: token record without user should not exist.
                $token->forceFill(['revoked_at' => now()])->save();
                throw new InvalidRefreshTokenException();
            }

            if (! ($user->is_active ?? true)) {
                $token->forceFill(['revoked_at' => now()])->save();
                throw new DeactivatedUserException();
            }

            if (! $user->hasVerifiedEmail()) {
                $token->forceFill(['revoked_at' => now()])->save();
                throw new UnverifiedEmailException();
            }

            // Rotate: revoke current token, issue a new refresh token linked to the previous record.
            $issued = $this->issueModelForUser($user, rotatedFromId: $token->id);
            $token->forceFill([
                'revoked_at' => now(),
                'rotated_to_id' => $issued['model']->id,
            ])->save();

            $newRefreshPlain = $issued['plain'];
            $accessToken = $user->createToken('auth')->plainTextToken;

            return [
                'user' => $user,
                'token' => $accessToken,
                'refresh_token' => $newRefreshPlain,
            ];
        });
    }

    public function issueForUser(User $user, ?int $rotatedFromId = null): string
    {
        return $this->issueModelForUser($user, $rotatedFromId)['plain'];
    }

    public function revokeByPlainToken(?string $plainRefreshToken): void
    {
        if (! $plainRefreshToken) {
            return;
        }

        $hash = self::hashToken($plainRefreshToken);

        RefreshToken::query()
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(User $user): void
    {
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private static function generateToken(): string
    {
        // 64 hex chars (256-bit) - safe for cookies and hashing.
        return bin2hex(random_bytes(32));
    }

    private static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    private function findByPlainTokenForUpdate(string $plainRefreshToken): ?RefreshToken
    {
        if ($plainRefreshToken === '') {
            return null;
        }

        $hash = self::hashToken($plainRefreshToken);

        /** @var RefreshToken|null $token */
        $token = RefreshToken::query()
            ->where('token_hash', $hash)
            ->lockForUpdate()
            ->first();

        return $token;
    }

    /**
     * Resolve a refresh token record for rotation. This tolerates near-parallel refreshes:
     * when the presented token was revoked due to rotation, we follow rotated_to_id for a
     * short grace window and rotate the successor instead of throwing 401.
     *
     * @throws InvalidRefreshTokenException
     */
    private function resolveTokenForRotation(RefreshToken $token): RefreshToken
    {
        $graceSeconds = (int) config('refresh_tokens.rotation_grace_seconds', 30);
        $graceSeconds = max(0, $graceSeconds);
        $cutoff = now()->subSeconds($graceSeconds);

        for ($hops = 0; $hops < 10; $hops++) {
            if ($token->expires_at->isPast()) {
                throw new InvalidRefreshTokenException();
            }

            if ($token->revoked_at === null) {
                return $token;
            }

            $withinGrace = $graceSeconds > 0 && $token->revoked_at->greaterThanOrEqualTo($cutoff);
            if (! $withinGrace || ! $token->rotated_to_id) {
                throw new InvalidRefreshTokenException();
            }

            $next = RefreshToken::query()
                ->whereKey($token->rotated_to_id)
                ->lockForUpdate()
                ->first();

            if (! $next) {
                throw new InvalidRefreshTokenException();
            }

            $token = $next;
        }

        throw new InvalidRefreshTokenException();
    }

    /**
     * @return array{plain: string, model: RefreshToken}
     */
    private function issueModelForUser(User $user, ?int $rotatedFromId = null): array
    {
        $ttlDays = (int) config('refresh_tokens.cookie.ttl_days', 30);
        $expiresAt = now()->addDays(max(1, $ttlDays));

        // Extremely low probability of collision, but loop defensively on token_hash uniqueness.
        for ($i = 0; $i < 5; $i++) {
            $plain = self::generateToken();
            $hash = self::hashToken($plain);

            try {
                $model = RefreshToken::query()->create([
                    'user_id' => $user->id,
                    'token_hash' => $hash,
                    'expires_at' => $expiresAt,
                    'revoked_at' => null,
                    'rotated_from_id' => $rotatedFromId,
                    'rotated_to_id' => null,
                ]);

                return [
                    'plain' => $plain,
                    'model' => $model,
                ];
            } catch (\Illuminate\Database\QueryException $e) {
                // Retry only on unique collision; otherwise rethrow.
                $isUnique = str_contains(strtolower($e->getMessage()), 'unique');
                if (! $isUnique) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Failed to generate unique refresh token.');
    }
}

