<?php

declare(strict_types=1);

namespace Sunrice\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Password reset email for the CMS admin. Laravel's default notification
 * links to the host app's `password.reset` route, which may not exist (or
 * may belong to the app's own front-end auth), so this one links to the
 * Sunrice reset page instead.
 */
class ResetAdminPassword extends ResetPassword
{
    protected function resetUrl(mixed $notifiable): string
    {
        return url(route('sunrice.admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
