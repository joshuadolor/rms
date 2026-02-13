<?php

namespace App\Application\Auth;

use Illuminate\Support\Facades\Password;

final readonly class ForgotPassword
{
    /**
     * Always return the same generic message and do not reveal whether the email exists.
     *
     * @param array{email: string} $input
     */
    public function handle(array $input): string
    {
        Password::sendResetLink($input);

        return __('If that email exists in our system, we have sent a password reset link.');
    }
}
