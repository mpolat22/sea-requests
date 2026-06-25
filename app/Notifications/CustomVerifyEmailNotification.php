<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verifyUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Sea Requests | Verify Your Email Address')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Please verify your email address to activate your Sea Requests account.')
            ->line('Once your email address is verified, you can complete your supplier verification form and upload the required documents for admin review.')
            ->action('Verify Email Address', $verifyUrl)
            ->line('If you did not create this account, no further action is required.')
            ->salutation('Sea Requests Team');
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
