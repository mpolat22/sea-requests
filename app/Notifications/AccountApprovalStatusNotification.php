<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountApprovalStatusNotification extends Notification
{
    public function __construct(private readonly string $status)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->status === 'approved') {
            return (new MailMessage)
                ->subject('Sea Requests | Account Approved')
                ->greeting('Hello '.$notifiable->name.',')
                ->line('Your Sea Requests account has been approved by our team.')
                ->line('You can now sign in and start using the platform.')
                ->action('Open Dashboard', url('/dashboard'))
                ->salutation('Sea Requests Team');
        }

        return (new MailMessage)
            ->subject('Sea Requests | Account Status Update')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your Sea Requests account was not approved at this time.')
            ->line('Please review the details in your account and submit an updated application if needed.')
            ->action('Open Application Page', url('/approval-pending'))
            ->salutation('Sea Requests Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $approved = $this->status === 'approved';
        $copy = [
            'title' => $approved ? 'Your account has been approved' : 'Your application was rejected',
            'message' => $approved
                ? 'Your application has been approved. You can now start using your account.'
                : 'Your application was not approved at this time. You can update your details and apply again.',
            'action_label' => $approved ? 'Open Dashboard' : 'Open Application Page',
        ];

        return [
            'title' => $copy['title'],
            'message' => $copy['message'],
            'action_label' => $copy['action_label'],
            'action_url' => $approved ? url('/dashboard') : url('/approval-pending'),
            'tone' => $approved ? 'success' : 'error',
            'translations' => [
                'en' => $copy,
            ],
        ];
    }
}
