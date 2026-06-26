<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Support\MarketplaceNotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MarketplaceNotificationMailRoutingTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_marketplace_notification_defaults_to_admin_mail_for_admin_recipients(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'admin@searequests.ai',
            'mail.from.name' => 'Sea Requests Admin',
            'mail.requests_mail.from.address' => 'requests@searequests.ai',
            'mail.requests_mail.from.name' => 'Sea Requests Requests',
        ]);

        $notification = new MarketplaceNotification([
            'en' => [
                'subject' => 'Sea Requests | New Supplier Application',
                'title' => 'New Supplier Application',
                'message' => 'A new supplier application has been submitted.',
                'details' => [],
                'action_label' => 'Review Application',
            ],
            'action_url' => 'https://searequests.ai/dashboard/admin',
        ]);

        $mail = $notification->toMail((object) [
            'name' => 'Admin User',
            'role' => 'admin',
        ]);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('smtp', $mail->mailer);
        $this->assertSame(['admin@searequests.ai', 'Sea Requests Admin'], $mail->from);
        $this->assertSame('Sea Requests | New Supplier Application', $mail->subject);
    }

    public function test_business_application_received_uses_admin_mail_profile_for_supplier(): void
    {
        Notification::fake();

        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'admin@searequests.ai',
            'mail.from.name' => 'Sea Requests Admin',
            'mail.requests_mail.from.address' => 'requests@searequests.ai',
            'mail.requests_mail.from.name' => 'Sea Requests Requests',
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'name' => 'Supplier User',
            'company_name' => 'Demo Supplier',
        ]);

        MarketplaceNotificationCenter::notifySellerVerificationSubmitted($seller);

        Notification::assertSentTo(
            $seller,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller) {
                $mail = $notification->toMail($seller);

                return in_array('mail', $channels, true)
                    && $mail instanceof MailMessage
                    && $mail->mailer === 'smtp'
                    && $mail->from === ['admin@searequests.ai', 'Sea Requests Admin']
                    && $mail->subject === 'Sea Requests | Business Application Received';
            }
        );
    }

    public function test_update_status_notifications_use_admin_mail_profile_for_supplier(): void
    {
        Notification::fake();

        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'admin@searequests.ai',
            'mail.from.name' => 'Sea Requests Admin',
            'mail.requests_mail.from.address' => 'requests@searequests.ai',
            'mail.requests_mail.from.name' => 'Sea Requests Requests',
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'name' => 'Supplier User',
            'company_name' => 'Demo Supplier',
        ]);

        MarketplaceNotificationCenter::notifySellerUpdateRequestSubmitted($seller);
        MarketplaceNotificationCenter::notifySellerUpdateRequestReviewed($seller, true);
        MarketplaceNotificationCenter::notifySellerUpdateRequestReviewed($seller, false, [
            'note' => 'Please update the business details.',
        ]);

        Notification::assertSentToTimes($seller, MarketplaceNotification::class, 3);

        $sent = Notification::sent(
            $seller,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller) {
            $mail = $notification->toMail($seller);

            $this->assertInstanceOf(MailMessage::class, $mail);
            $this->assertContains('mail', $channels);
            $this->assertSame('smtp', $mail->mailer);
            $this->assertSame(['admin@searequests.ai', 'Sea Requests Admin'], $mail->from);
            $this->assertContains($mail->subject, [
                'Sea Requests | Update Request Received',
                'Sea Requests | Update Approved',
                'Sea Requests | Update Rejected',
            ]);

                return true;
            }
        );

        $this->assertCount(3, $sent);
    }
}
