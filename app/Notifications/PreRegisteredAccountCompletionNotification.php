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
        $email = $notifiable->email;

        return $isSupplier
            ? $this->supplierMail($companyName, $email)
            : $this->buyerMail($companyName, $email);
    }

    private function supplierMail(string $companyName, string $email): MailMessage
    {
        return (new MailMessage)
            ->mailer('support')
            ->from((string) config('mail.support_mail.from.address', 'support@searequests.ai'), (string) config('mail.support_mail.from.name', 'Sea Requests Support'))
            ->subject('Complete Your Supplier Profile on SeaRequests.ai')
            ->greeting('Dear '.$companyName.' Team,')
            ->line('A preliminary supplier profile has been created for your company on SeaRequests.ai, a platform connecting marine suppliers with shipowners, vessel operators, ship managers, and purchasing companies.')
            ->line('Your preliminary profile has been created using the following email address:')
            ->line($email)
            ->line('To activate your account and participate fully on the platform, please complete your company profile within 14 days.')
            ->line('Please add or confirm:')
            ->line('- company information;')
            ->line('- contact details;')
            ->line('- products and services offered;')
            ->line('- service regions and ports;')
            ->line('- company documents and certificates, where applicable;')
            ->line('- company brochure or catalogue, where applicable;')
            ->line('- account password.')
            ->action('Complete Supplier Profile', $this->completionUrl)
            ->line('Participation and company registration are currently free of charge.')
            ->line('Once your profile is completed, your company will be able to receive relevant requests for quotations and submit commercial offers through the platform.')
            ->line('We hope your company will join SeaRequests.ai and become an active participant in our international marine supply network.')
            ->salutation("Best regards,\nMuhammad Zumar\nSupport Team | SeaRequests.ai\nEmail: support@searequests.ai\nWebsite: www.searequests.ai\nWhatsApp: +90 507 814 91 76");
    }

    private function buyerMail(string $companyName, string $email): MailMessage
    {
        return (new MailMessage)
            ->mailer('support')
            ->from((string) config('mail.support_mail.from.address', 'support@searequests.ai'), (string) config('mail.support_mail.from.name', 'Sea Requests Support'))
            ->subject('Complete Your Buyer Profile on SeaRequests.ai')
            ->greeting('Dear '.$companyName.' Team,')
            ->line('A preliminary buyer profile has been created for your organization on SeaRequests.ai, a platform designed to help shipowners, vessel operators, ship managers, and purchasing companies request and compare quotations from marine suppliers.')
            ->line('Your preliminary profile has been created using the following email address:')
            ->line($email)
            ->line('To activate your account and receive full access to the platform, please complete your profile within 14 days.')
            ->line('Please add or confirm:')
            ->line('- company information;')
            ->line('- contact details;')
            ->line('- company role and business activity;')
            ->line('- vessel or fleet information, where applicable;')
            ->line('- purchasing regions and ports, where applicable;')
            ->line('- account password.')
            ->action('Complete Buyer Profile', $this->completionUrl)
            ->line('Participation and account registration are currently free of charge.')
            ->line('After activation, your company will be able to create requests for quotations, receive offers from relevant suppliers, compare prices and conditions, and communicate with selected suppliers through the platform.')
            ->line('We hope SeaRequests.ai will become a useful sourcing tool for your company.')
            ->salutation("Best regards,\nMuhammad Zumar\nSupport Team | SeaRequests.ai\nEmail: support@searequests.ai\nWebsite: www.searequests.ai\nWhatsApp: +90 507 814 91 76");
    }
}
