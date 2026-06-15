<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class CustomResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Sea Requests | Password Reset Request')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset the password for your Sea Requests account.')
            ->action('Reset Password', $resetUrl)
            ->line('This password reset link will remain valid for 60 minutes.')
            ->line('If you did not request a password reset, you can safely ignore this email.')
            ->salutation('Sea Requests Team');
    }

    protected function resetUrl(object $notifiable): string
    {
        return URL::to(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ], false));
    }
}
