<?php

namespace App\Application\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class LogoutUser
{
    public function handle(Authenticatable $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
