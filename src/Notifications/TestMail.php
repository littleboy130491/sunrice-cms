<?php

declare(strict_types=1);

namespace Sunrice\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The "Send test email" message from Settings → Email. */
class TestMail extends Notification
{
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

        return (new MailMessage)
            ->subject("Test email from {$site}")
            ->line("This is a test email from {$site}.")
            ->line('If you can read it, outgoing mail works: login codes, password resets and form notifications can be delivered.');
    }
}
