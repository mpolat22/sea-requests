<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MarketplaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SellerVerificationReminderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_seller_receives_onboarding_notification_after_email_verification(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'admin@searequests.ai',
            'mail.from.name' => 'Sea Requests Admin',
            'mail.requests_mail.from.address' => 'requests@searequests.ai',
            'mail.requests_mail.from.name' => 'Sea Requests Requests',
        ]);

        Notification::fake();

        $seller = User::factory()->unverified()->create([
            'role' => 'seller',
            'email' => 'seller-onboarding@example.test',
            'approval_status' => 'pending',
            'approved_at' => null,
        ]);

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $seller->id,
                'hash' => sha1($seller->email),
            ]
        );

        $this->actingAs($seller)
            ->get($verifyUrl)
            ->assertRedirect(route('seller.verification.create'))
            ->assertSessionHas('success', 'email-verified');

        $seller->refresh();

        $this->assertNotNull($seller->email_verified_at);
        $this->assertNotNull($seller->seller_verification_onboarding_sent_at);

        Notification::assertSentTo(
            $seller,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller): bool {
                $payload = $notification->toArray($seller);
                $mail = $notification->toMail($seller);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Complete Your Supplier Verification'
                    && ($payload['action_url'] ?? null) === route('seller.verification.create')
                    && $mail->from === ['admin@searequests.ai', 'Sea Requests Admin'];
            }
        );
    }

    public function test_reminder_command_sends_due_notifications_once_and_stops_after_final_reminder(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'admin@searequests.ai',
            'mail.from.name' => 'Sea Requests Admin',
            'mail.requests_mail.from.address' => 'requests@searequests.ai',
            'mail.requests_mail.from.name' => 'Sea Requests Requests',
        ]);

        Notification::fake();

        $fallbackOnboardingSeller = User::factory()->create([
            'role' => 'seller',
            'email' => 'seller-fallback@example.test',
            'email_verified_at' => now()->subHours(2),
            'approval_status' => 'pending',
            'approved_at' => null,
            'seller_verification_submitted_at' => null,
            'seller_verification_onboarding_sent_at' => null,
        ]);

        $seller24 = User::factory()->create([
            'role' => 'seller',
            'email' => 'seller-24h@example.test',
            'email_verified_at' => now()->subHours(25),
            'approval_status' => 'pending',
            'approved_at' => null,
            'seller_verification_submitted_at' => null,
            'seller_verification_onboarding_sent_at' => now()->subHours(25),
            'seller_verification_24h_reminder_sent_at' => null,
            'seller_verification_72h_reminder_sent_at' => null,
        ]);

        $seller72 = User::factory()->create([
            'role' => 'seller',
            'email' => 'seller-72h@example.test',
            'email_verified_at' => now()->subHours(73),
            'approval_status' => 'pending',
            'approved_at' => null,
            'seller_verification_submitted_at' => null,
            'seller_verification_onboarding_sent_at' => now()->subHours(73),
            'seller_verification_24h_reminder_sent_at' => now()->subHours(49),
            'seller_verification_72h_reminder_sent_at' => null,
        ]);

        $submittedSeller = User::factory()->create([
            'role' => 'seller',
            'email' => 'seller-submitted@example.test',
            'email_verified_at' => now()->subHours(80),
            'approval_status' => 'pending',
            'approved_at' => null,
            'seller_verification_submitted_at' => now()->subHours(40),
            'seller_verification_onboarding_sent_at' => now()->subHours(79),
            'seller_verification_24h_reminder_sent_at' => now()->subHours(55),
            'seller_verification_72h_reminder_sent_at' => null,
        ]);

        $finalAlreadySentSeller = User::factory()->create([
            'role' => 'seller',
            'email' => 'seller-final@example.test',
            'email_verified_at' => now()->subHours(90),
            'approval_status' => 'pending',
            'approved_at' => null,
            'seller_verification_submitted_at' => null,
            'seller_verification_onboarding_sent_at' => now()->subHours(89),
            'seller_verification_24h_reminder_sent_at' => now()->subHours(66),
            'seller_verification_72h_reminder_sent_at' => now()->subHours(18),
        ]);

        $this->artisan('seller-verification:send-reminders')
            ->expectsOutput('Seller verification reminders sent. onboarding=1, reminder_24h=1, reminder_72h=1')
            ->assertExitCode(0);

        $fallbackOnboardingSeller->refresh();
        $seller24->refresh();
        $seller72->refresh();
        $submittedSeller->refresh();
        $finalAlreadySentSeller->refresh();

        $this->assertNotNull($fallbackOnboardingSeller->seller_verification_onboarding_sent_at);
        $this->assertNotNull($seller24->seller_verification_24h_reminder_sent_at);
        $this->assertNotNull($seller72->seller_verification_72h_reminder_sent_at);
        $this->assertNull($submittedSeller->seller_verification_72h_reminder_sent_at);
        $this->assertNotNull($finalAlreadySentSeller->seller_verification_72h_reminder_sent_at);

        Notification::assertSentTo(
            $fallbackOnboardingSeller,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($fallbackOnboardingSeller): bool {
                $payload = $notification->toArray($fallbackOnboardingSeller);
                $mail = $notification->toMail($fallbackOnboardingSeller);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Complete Your Supplier Verification'
                    && $mail->from === ['admin@searequests.ai', 'Sea Requests Admin'];
            }
        );

        Notification::assertSentTo(
            $seller24,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller24): bool {
                $payload = $notification->toArray($seller24);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Supplier Verification Reminder';
            }
        );

        Notification::assertSentTo(
            $seller72,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller72): bool {
                $payload = $notification->toArray($seller72);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Final Supplier Verification Reminder';
            }
        );

        Notification::assertNotSentTo($submittedSeller, MarketplaceNotification::class);
        Notification::assertNotSentTo($finalAlreadySentSeller, MarketplaceNotification::class);

        $this->artisan('seller-verification:send-reminders')
            ->expectsOutput('Seller verification reminders sent. onboarding=0, reminder_24h=0, reminder_72h=0')
            ->assertExitCode(0);

        $this->assertCount(1, Notification::sent($fallbackOnboardingSeller, MarketplaceNotification::class));
        $this->assertCount(1, Notification::sent($seller24, MarketplaceNotification::class));
        $this->assertCount(1, Notification::sent($seller72, MarketplaceNotification::class));
    }
}
