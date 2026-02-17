<?php

namespace App\Services;

use App\Models\User;

class E2eCleanupService
{
    /**
     * Delete users whose email matches any of the configured E2E patterns.
     * Does not check APP_ENV; caller must ensure this is only run in local/dev.
     *
     * @return int Number of users deleted
     */
    public function deleteE2eUsers(): int
    {
        $patterns = config('e2e.cleanup_email_patterns', ['e2e-%@example.com']);

        if (empty($patterns)) {
            return 0;
        }

        $users = User::where(function ($query) use ($patterns): void {
            foreach ($patterns as $pattern) {
                $query->orWhere('email', 'like', $pattern);
            }
        })->get();

        $deleted = 0;
        foreach ($users as $user) {
            $user->tokens()->delete();
            $user->delete();
            $deleted++;
        }

        return $deleted;
    }
}
