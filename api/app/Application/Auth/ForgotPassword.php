<?php

namespace App\Application\Auth;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

final readonly class ForgotPassword
{
    /**
     * Send password reset link. On success returns success message (do not reveal whether email exists).
     * When we know the email was not sent (APP_KEY missing, or send failed), returns failure so the
     * API can respond with 503 and the frontend can show an error instead of false hope.
     *
     * @param array{email: string} $input
     * @return array{success: bool, message: string}
     */
    public function handle(array $input): array
    {
        $successMessage = __('If that email exists in our system, we have sent a password reset link.');
        $failureMessage = __('We couldn\'t send the reset link right now. Please try again in a few minutes.');

        $appKey = config('app.key');
        if (empty($appKey) || ! is_string($appKey)) {
            Log::critical('Forgot password: APP_KEY is not set. Password reset emails cannot be sent. Run php artisan key:generate and set APP_KEY in .env');

            return ['success' => false, 'message' => $failureMessage];
        }

        try {
            Password::sendResetLink($input);

            return ['success' => true, 'message' => $successMessage];
        } catch (\Throwable $e) {
            Log::warning('Forgot password: failed to send reset link', [
                'email' => $input['email'] ?? null,
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
            Log::debug('Forgot password: reset link exception trace', [
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'message' => $failureMessage];
        }
    }
}
