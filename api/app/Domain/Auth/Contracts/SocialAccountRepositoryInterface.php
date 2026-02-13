<?php

namespace App\Domain\Auth\Contracts;

use App\Models\SocialAccount;
use App\Models\User;

interface SocialAccountRepositoryInterface
{
    public function findByProviderAndProviderId(string $provider, string $providerId): ?SocialAccount;

    public function createForUser(User $user, string $provider, string $providerId): SocialAccount;
}
