<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreRegisteredAccountRemovedNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $companyName = $notifiable->company_name ?: $notifiable->name;

        return (new MailMessage)
            ->mailer('support')
            ->from((string) config('mail.support_mail.from.address', 'support@searequests.ai'), (string) config('mail.support_mail.from.name', 'Sea Requests Support'))
            ->subject('Sea Requests | Pre-Registration Removed')
            ->greeting('Hello '.$companyName.',')
            ->line('Your company pre-registration on Sea Requests has been removed because the registration process was not completed within the required timeframe.')
            ->line('Your company profile and pre-registration data are no longer active on the platform.')
            ->line('If you would like to join Sea Requests in the future, you can register again from the platform.')
            ->salutation("Best regards,\nMuhammad Zumar\nSupport Team | SeaRequests.ai\nEmail: support@searequests.ai\nWebsite: www.searequests.ai\nWhatsApp: +90 507 814 91 76");
    }
}