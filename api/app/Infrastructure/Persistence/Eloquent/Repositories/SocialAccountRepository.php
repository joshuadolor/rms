<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Auth\Contracts\SocialAccountRepositoryInterface;
use App\Models\SocialAccount;
use App\Models\User;

final class SocialAccountRepository implements SocialAccountRepositoryInterface
{
    public function findByProviderAndProviderId(string $provider, string $providerId): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();
    }

    public function createForUser(User $user, string $provider, string $providerId): SocialAccount
    {
        return SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $providerId,
        ]);
    }
}
