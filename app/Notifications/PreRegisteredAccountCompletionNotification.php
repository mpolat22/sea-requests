<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreRegisteredAccountCompletionNotification extends Notification
{
    public function __construct(
        private readonly string $completionUrl,
        private readonly string $accountType
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isSupplier = $this->accountType === 'seller';
        $companyName = $notifiable->company_name ?: $notifiable->name;

        return (new MailMessage)
            ->subject($isSupplier
                ? 'Sea Requests | Complete Your Supplier Account'
                : 'Sea Requests | Complete Your Buyer Account')
            ->greeting('Hello '.$companyName.',')
            ->line($isSupplier
                ? 'Your company profile has been pre-registered on Sea Requests as a supplier.'
                : 'Your company account has been pre-registered on Sea Requests as a buyer.')
            ->line($isSupplier
                ? 'Please set your password, review the pre-filled company details, and complete supplier verification before your public listing and RFQ access become active.'
                : 'Please set your password to access your buyer dashboard and start managing procurement requests.')
            ->action($isSupplier ? 'Complete Supplier Account' : 'Complete Buyer Account', $this->completionUrl)
            ->line('For security, this link is intended only for the owner of this company email address.')
            ->salutation('Sea Requests Team');
    }
}
