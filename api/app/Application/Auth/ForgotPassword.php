<?php

namespace App\Application\Auth;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

final readonly class ForgotPassword
{
    /**
     * Always return the same generic message and do not reveal whether the email exists.
     * On mail/notification failure we log and still return the generic message (no 500).
     *
     * @param array{email: string} $input
     */
    public function handle(array $input): string
    {
        $message = __('If that email exists in our system, we have sent a password reset link.');

        try {
            Password::sendResetLink($input);
        } catch (\Throwable $e) {
            Log::warning('Forgot password: failed to send reset link', [
                'email' => $input['email'] ?? null,
                'exception' => $e->getMessage(),
            ]);
        }

        return $message;
    }
}
