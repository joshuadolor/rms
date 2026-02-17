<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyNewEmailNotification extends Notification
{

    /**
     * Build the verification URL for the new email. Link points to frontend which then calls API.
     */
    public function verificationUrl($notifiable): string
    {
        $pendingEmail = $notifiable->pending_email;
        if (! $pendingEmail) {
            return '';
        }
        $expiration = Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60));
        $uuid = $notifiable->uuid;
        $hash = sha1(strtolower($pendingEmail));

        $apiUrl = URL::temporarySignedRoute(
            'api.verification.verify-new',
            $expiration,
            ['uuid' => $uuid, 'hash' => $hash]
        );

        $parsed = parse_url($apiUrl);
        $query = $parsed['query'] ?? '';
        parse_str($query, $params);

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $params = array_merge(['uuid' => $uuid, 'hash' => $hash], $params);

        return $frontendUrl . '/email/verify-new?' . http_build_query($params);
    }

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(__('Verify your new email address'))
            ->line(__('You requested to change your email address. Please click the button below to verify your new email.'))
            ->action(__('Verify new email'), $url)
            ->line(__('If you did not request this change, you can safely ignore this email.'));
    }
}
