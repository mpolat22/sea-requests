<?php

namespace App\Support\Outreach;

use App\Models\OutreachSenderAccount;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class OutreachDynamicMailer
{
    public function send(?OutreachSenderAccount $senderAccount, string $recipientEmail, Mailable $message): string
    {
        if (! $senderAccount) {
            Mail::to($recipientEmail)->send($message);

            return (string) config('mail.from.address');
        }

        $mailerName = $this->mailerName($senderAccount);

        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'scheme' => $this->mailScheme($senderAccount->smtp_encryption),
                'host' => $senderAccount->smtp_host,
                'port' => (int) $senderAccount->smtp_port,
                'username' => $senderAccount->smtp_username,
                'password' => $senderAccount->smtp_password,
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST)),
            ],
        ]);

        $this->purgeMailer($mailerName);

        try {
            Mail::mailer($mailerName)->to($recipientEmail)->send($message);
        } finally {
            $this->purgeMailer($mailerName);
        }

        return $senderAccount->from_email;
    }

    private function mailScheme(?string $encryption): ?string
    {
        return match ($encryption) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => null,
        };
    }

    private function mailerName(OutreachSenderAccount $senderAccount): string
    {
        if ($senderAccount->getKey()) {
            return 'outreach-sender-'.$senderAccount->getKey();
        }

        return 'outreach-sender-preview-'.substr(md5(implode('|', [
            (string) $senderAccount->from_email,
            (string) $senderAccount->smtp_host,
            (string) $senderAccount->smtp_port,
            (string) $senderAccount->smtp_username,
        ])), 0, 12);
    }

    private function purgeMailer(string $mailerName): void
    {
        $manager = app('mail.manager');

        if (is_object($manager) && method_exists($manager, 'purge')) {
            $manager->purge($mailerName);
        }
    }
}
