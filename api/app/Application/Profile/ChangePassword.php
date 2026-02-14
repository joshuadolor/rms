<?php

namespace App\Application\Profile;

use App\Models\User;

final readonly class ChangePassword
{
    public function handle(User $user, string $newPassword): void
    {
        // password is hashed by the User cast, but we still avoid mass assignment for consistency.
        $user->forceFill([
            'password' => $newPassword,
        ])->save();
    }
}

