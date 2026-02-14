<?php

namespace App\Application\Profile;

use App\Models\User;
use App\Notifications\VerifyNewEmailNotification;
use Illuminate\Support\Str;

final readonly class UpdateProfile
{
    /**
     * @param User $user Authenticated user
     * @param array{name?: string, email?: string} $input Validated input
     * @return array{user: User, message: string, email_change_pending: bool}
     */
    public function handle(User $user, array $input): array
    {
        if (array_key_exists('name', $input)) {
            $user->name = $input['name'];
        }

        $emailChanged = array_key_exists('email', $input)
            && Str::lower($input['email']) !== Str::lower($user->email);

        if ($emailChanged) {
            // pending_email is intentionally NOT mass assignable (security). Use forceFill.
            $user->forceFill([
                'pending_email' => Str::lower($input['email']),
            ])->save();

            $user->notify(new VerifyNewEmailNotification);

            return [
                'user' => $user,
                'message' => __('A verification link has been sent to your new email address. Please confirm to complete the change.'),
                'email_change_pending' => true,
            ];
        }

        $user->save();

        return [
            'user' => $user,
            'message' => __('Profile updated.'),
            'email_change_pending' => false,
        ];
    }
}

