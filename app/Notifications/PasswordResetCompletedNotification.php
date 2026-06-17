<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCompletedNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sea Requests | Your Password Has Been Reset')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your Sea Requests account password has been reset successfully.')
            ->line('You can now sign in with your new password.')
            ->action('Open Login Page', route('login'))
            ->line('If you did not reset your password, please contact our support team immediately.')
            ->salutation('Sea Requests Team');
    }
}
