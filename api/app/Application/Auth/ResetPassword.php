<?php

namespace App\Application\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ResetPassword
{
    /**
     * @param array{token: string, email: string, password: string, password_confirmation: string} $input
     * @throws ValidationException
     */
    public function handle(array $input): string
    {
        $status = Password::reset(
            $input,
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__('This password reset token is invalid or has expired.')],
            ]);
        }

        return __('Your password has been reset.');
    }
}
