<?php

namespace App\Application\EmailVerification;

use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Validation\ValidationException;

final readonly class VerifyEmail
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * @return array{user: User, already_verified: bool}
     */
    public function handle(string $uuid, string $hash): array
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid verification link.')],
            ]);
        }

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid verification link.')],
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'user' => $user,
                'already_verified' => true,
            ];
        }

        // Signature is validated by the 'signed' route middleware.
        $user->forceFill(['email_verified_at' => $user->freshTimestamp()])->save();

        event(new Verified($user));

        return [
            'user' => $user,
            'already_verified' => false,
        ];
    }
}

