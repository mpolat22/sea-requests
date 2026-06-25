<?php

namespace Tests\Feature;

use App\Notifications\MarketplaceNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class MarketplaceNotificationMailRoutingTest extends TestCase
{
    public function test_marketplace_notification_uses_requests_mailer_and_from_address(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'admin@searequests.ai',
            'mail.from.name' => 'Sea Requests',
            'mail.requests_mail.from.address' => 'requests@searequests.ai',
            'mail.requests_mail.from.name' => 'Sea Requests Requests',
        ]);

        $notification = new MarketplaceNotification([
            'en' => [
                'subject' => 'Sea Requests | Workflow Update',
                'title' => 'Workflow Update',
                'message' => 'A workflow update is ready.',
                'details' => [
                    ['label' => 'Reference No', 'value' => 'RFQ-TEST-001'],
                ],
                'action_label' => 'Open Detail',
            ],
            'action_url' => 'https://searequests.ai/dashboard/buyer/orders/1',
        ]);

        $mail = $notification->toMail((object) ['name' => 'Mustafa']);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('requests', $mail->mailer);
        $this->assertSame(['requests@searequests.ai', 'Sea Requests Requests'], $mail->from);
        $this->assertSame('Sea Requests | Workflow Update', $mail->subject);
    }

    public function test_marketplace_notification_can_use_admin_mail_profile(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'admin@searequests.ai',
            'mail.from.name' => 'Sea Requests Admin',
            'mail.requests_mail.from.address' => 'requests@searequests.ai',
            'mail.requests_mail.from.name' => 'Sea Requests Requests',
        ]);

        $notification = new MarketplaceNotification([
            'mail_profile' => 'admin',
            'en' => [
                'subject' => 'Sea Requests | Application Rejected',
                'title' => 'Application Rejected',
                'message' => 'Your application was not approved at this time.',
                'details' => [],
                'action_label' => 'Edit Application',
            ],
            'action_url' => 'https://searequests.ai/seller/verification',
        ]);

        $mail = $notification->toMail((object) ['name' => 'Mustafa']);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('smtp', $mail->mailer);
        $this->assertSame(['admin@searequests.ai', 'Sea Requests Admin'], $mail->from);
        $this->assertSame('Sea Requests | Application Rejected', $mail->subject);
    }
}
