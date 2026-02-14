<?php

namespace App\Application\EmailVerification;

use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class VerifyNewEmail
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function handle(string $uuid, string $hash): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (! $user || ! $user->pending_email) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid or expired link.')],
            ]);
        }

        if (! hash_equals($hash, sha1(strtolower($user->pending_email)))) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid verification link.')],
            ]);
        }

        $user->forceFill([
            'email' => $user->pending_email,
            'pending_email' => null,
            'email_verified_at' => $user->freshTimestamp(),
        ])->save();

        return $user;
    }
}

