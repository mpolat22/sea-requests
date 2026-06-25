<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class MarketplaceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, array<string, mixed>|string|null>  $content
     * @param  array<int, string>  $channels
     */
    public function __construct(
        private readonly array $content,
        private readonly array $channels = ['mail', 'database']
    ) {
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $copy = $this->payload();
        $mailProfile = $this->mailProfileConfig();

        $mail = (new MailMessage)
            ->mailer($mailProfile['mailer'])
            ->from($mailProfile['from_address'], $mailProfile['from_name'])
            ->subject($copy['subject'])
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($copy['message']);

        foreach ($copy['details'] as $detail) {
            $label = e((string) ($detail['label'] ?? ''));
            $value = nl2br(e((string) ($detail['value'] ?? '')));
            $mail->line(new HtmlString("<strong>{$label}</strong><br>{$value}"));
        }

        $mail->salutation('Sea Requests Team');

        if (! empty($this->content['action_url']) && ! empty($copy['action_label'])) {
            $mail->action($copy['action_label'], (string) $this->content['action_url']);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $copy = $this->payload();

        return [
            'title' => $copy['title'],
            'message' => $copy['message'],
            'details' => $copy['details'],
            'action_label' => $copy['action_label'],
            'action_url' => $this->content['action_url'] ?? null,
            'tone' => $this->content['tone'] ?? 'info',
            'translations' => [
                'en' => [
                    'title' => $copy['title'],
                    'message' => $copy['message'],
                    'details' => $copy['details'],
                    'action_label' => $copy['action_label'],
                ],
            ],
        ];
    }

    /**
     * @return array{mailer:string,from_address:string,from_name:string}
     */
    private function mailProfileConfig(): array
    {
        $profile = strtolower(trim((string) ($this->content['mail_profile'] ?? 'requests')));

        return match ($profile) {
            'admin', 'default' => [
                'mailer' => (string) config('mail.default', 'smtp'),
                'from_address' => (string) config('mail.from.address'),
                'from_name' => (string) config('mail.from.name'),
            ],
            'support' => [
                'mailer' => 'support',
                'from_address' => (string) config('mail.support_mail.from.address', config('mail.from.address')),
                'from_name' => (string) config('mail.support_mail.from.name', config('mail.from.name')),
            ],
            default => [
                'mailer' => 'requests',
                'from_address' => (string) config('mail.requests_mail.from.address', config('mail.from.address')),
                'from_name' => (string) config('mail.requests_mail.from.name', config('mail.from.name')),
            ],
        };
    }

    /**
     * @return array{subject:string,title:string,message:string,details:array<int, mixed>,action_label:string}
     */
    private function payload(): array
    {
        /** @var array<string, mixed> $copy */
        $copy = is_array($this->content['en'] ?? null)
            ? $this->content['en']
            : $this->content;

        return [
            'subject' => (string) ($copy['subject'] ?? ''),
            'title' => (string) ($copy['title'] ?? ''),
            'message' => (string) ($copy['message'] ?? ''),
            'details' => is_array($copy['details'] ?? null) ? $copy['details'] : [],
            'action_label' => (string) ($copy['action_label'] ?? ''),
        ];
    }
}
