<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    /**
     * Build the verification URL pointing to the frontend. The frontend page will call
     * the API verify endpoint with the same signed params so the link opens in the SPA.
     */
    protected function verificationUrl($notifiable): string
    {
        $expiration = Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60));
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        $apiUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            $expiration,
            ['id' => $id, 'hash' => $hash]
        );

        $parsed = parse_url($apiUrl);
        $query = $parsed['query'] ?? '';
        parse_str($query, $params);

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $params = array_merge(['id' => $id, 'hash' => $hash], $params);

        return $frontendUrl . '/email/verify?' . http_build_query($params);
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(__('Verify Email Address'))
            ->line(__('Please click the button below to verify your email address.'))
            ->action(__('Verify Email Address'), $url)
            ->line(__('If you did not create an account, no further action is required.'));
    }
}
