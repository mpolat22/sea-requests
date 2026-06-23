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
}
