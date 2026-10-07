<?php

declare(strict_types=1);

namespace Sunrice\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Sunrice\Auth\TwoFactorLogin;

/**
 * The one-time code for two-factor login. Sent right away (not queued):
 * the person is waiting on the code screen.
 */
class LoginCode extends Notification
{
    public function __construct(public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $site = (string) config('app.name');
        $minutes = TwoFactorLogin::LIFETIME_MINUTES;

        return (new MailMessage)
            ->subject("Your {$site} login code: {$this->code}")
            ->line('Enter this code to finish logging in to the admin:')
            ->line("**{$this->code}**")
            ->line("It expires in {$minutes} minutes.")
            ->line('If you didn\'t try to log in, change your password: someone else knows it.');
    }
}
