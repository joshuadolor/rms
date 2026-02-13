<?php

namespace App\Application\Auth;

use App\Domain\Auth\Contracts\SocialAccountRepositoryInterface;
use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class SocialLogin
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SocialAccountRepositoryInterface $socialAccountRepository
    ) {}

    /**
     * Find or create user for the given provider identity; issue token.
     *
     * @param array{provider: string, provider_id: string, email: string|null, name: string} $input
     * @return array{user: User, token: string}
     */
    public function handle(array $input): array
    {
        $provider = $input['provider'];
        $providerId = $input['provider_id'];
        $email = $input['email'];
        $name = $input['name'];

        $social = $this->socialAccountRepository->findByProviderAndProviderId($provider, $providerId);

        if ($social) {
            $user = $social->user;
        } else {
            $user = $email ? $this->userRepository->findByEmail($email) : null;
            if (! $user) {
                $user = $this->userRepository->create([
                    'name' => $name,
                    'email' => $email ?? $provider . '_' . $providerId . '@placeholder.rms.local',
                    'password' => bcrypt(Str::random(32)),
                ]);
            }
            $this->socialAccountRepository->createForUser($user, $provider, $providerId);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
