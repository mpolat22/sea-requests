<?php

namespace Tests\Feature;

use App\Jobs\SendOnboardingCompletionEmail;
use App\Models\OutreachContact;
use App\Models\User;
use App\Notifications\PreRegisteredAccountCompletionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminOnboardingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_onboarding_workspace(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.onboarding'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Onboarding/Index')
                ->where('activeTab', 'onboarding')
                ->where('dashboard.navigation.onboarding_count', 0)
            );
    }

    public function test_admin_can_create_supplier_onboarding_account_and_send_completion_email_automatically(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.onboarding.manual.store'), [
                'audience' => 'seller',
                'company_name' => 'Anchor Industries (Pty) Ltd',
                'contact_email' => 'sales@anchors.co.za',
                'phone' => '+27215310525',
                'website_url' => 'www.anchors.co.za',
                'country' => 'South Africa',
                'city' => 'Cape Town',
                'postal_code' => '7405',
                'address' => 'Old Mill Road 20',
                'business_activity' => 'Marine equipment suppliers and ship chandlers.',
                'serviced_ports' => "Cape Town / South Africa
Durban / South Africa",
                'company_overview' => 'Anchor Industries offers engineering services and equipment for lifting, rigging, marine and offshore mooring.',
            ])
            ->assertRedirect();

        $contact = OutreachContact::query()->where('email', 'sales@anchors.co.za')->firstOrFail();

        $user = User::query()->where('email', 'sales@anchors.co.za')->firstOrFail();

        $this->assertTrue($user->isSeller());
        $this->assertSame('pending', $user->approval_status);
        $this->assertSame('Anchor Industries (Pty) Ltd', $contact->organization_name);
        $this->assertSame('Cape Town', data_get($contact->source_payload, 'parsed.city'));
        $this->assertSame('Cape Town', data_get($contact->source_payload, 'parsed.serviced_ports.0.port'));
        $this->assertSame('South Africa', data_get($contact->source_payload, 'parsed.serviced_ports.0.country'));

        $user->refresh();
        $contact->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->seller_verification_onboarding_sent_at);
        $this->assertSame('email_sent', data_get($contact->source_payload, 'onboarding_status'));

        Notification::assertSentTo($user, PreRegisteredAccountCompletionNotification::class);
    }


    public function test_admin_can_create_manual_ready_onboarding_profile(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.onboarding.manual.store'), [
                'audience' => 'seller',
                'company_name' => 'Manual Marine Supply Ltd',
                'email' => 'manual@example.test',
                'phone' => '+905550000000',
                'website_url' => 'www.manual-marine.example',
                'country' => 'Turkey',
                'city' => 'Istanbul',
                'postal_code' => '34900',
                'address' => 'Tuzla Shipyard Area',
                'business_activity' => 'Marine equipment suppliers and ship chandlers.',
                'serviced_ports' => "Istanbul / Turkey
Izmir / Turkey",
                'company_overview' => 'Manual Marine Supply Ltd supports ship owners with marine equipment and spare parts.',
            ])
            ->assertRedirect();

        $contact = OutreachContact::query()->where('email', 'manual@example.test')->firstOrFail();

        $user = User::query()->where('email', 'manual@example.test')->firstOrFail();

        $this->assertSame('email_sent', data_get($contact->source_payload, 'onboarding_status'));
        $this->assertSame('manual_profile', data_get($contact->source_payload, 'created_from'));
        $this->assertSame('Manual Marine Supply Ltd', $contact->organization_name);
        $this->assertSame('Turkey', data_get($contact->source_payload, 'parsed.country'));
        $this->assertSame('Istanbul', data_get($contact->source_payload, 'parsed.serviced_ports.0.port'));
        $this->assertSame('Turkey', data_get($contact->source_payload, 'parsed.serviced_ports.0.country'));
        $this->assertNotNull($user->email_verified_at);

        Notification::assertSentTo($user, PreRegisteredAccountCompletionNotification::class);
    }


    public function test_admin_can_bulk_import_company_name_email_file_and_queue_completion_emails(): void
    {
        Notification::fake();
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->createWithContent(
            'companies.csv',
            "Company Name,Email\nAnchor Industries,sales@anchors.co.za\nDivetech Marine,info@divetechuae.com\nAnchor Duplicate,sales@anchors.co.za\n"
        );

        $this->actingAs($admin)
            ->post(route('admin.onboarding.bulk-import.store'), [
                'audience' => 'seller',
                'file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $anchor = User::query()->where('email', 'sales@anchors.co.za')->firstOrFail();
        $divetech = User::query()->where('email', 'info@divetechuae.com')->firstOrFail();

        $this->assertTrue($anchor->isSeller());
        $this->assertSame('pending', $anchor->approval_status);
        $this->assertNotNull($anchor->email_verified_at);
        $this->assertNull($anchor->seller_verification_onboarding_sent_at);
        $this->assertSame('Anchor Industries', $anchor->company_name);
        $this->assertSame('Divetech Marine', $divetech->company_name);

        $contact = OutreachContact::query()->where('email', 'sales@anchors.co.za')->firstOrFail();
        $this->assertSame('bulk_company_import', data_get($contact->source_payload, 'created_from'));
        $this->assertSame('email_queued', data_get($contact->source_payload, 'onboarding_status'));
        $this->assertSame(2, data_get($contact->source_payload, 'completion_email_queue_interval_minutes'));
        $this->assertNotNull(data_get($contact->source_payload, 'completion_email_scheduled_for'));
        $this->assertNotNull(data_get($contact->source_payload, 'bulk_import_expires_at'));

        Queue::assertPushed(SendOnboardingCompletionEmail::class, 2);
        Queue::assertPushed(SendOnboardingCompletionEmail::class, fn ($job) => $job->contactId === $contact->id);
        Notification::assertNothingSent();
    }
    public function test_bulk_import_skips_existing_accounts_and_creates_only_new_rows(): void
    {
        Notification::fake();
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'role' => 'seller',
            'email' => 'old@example.test',
            'company_name' => 'Old Marine Supplier',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'companies.csv',
            "Company Name,Email\nOld Marine Supplier,old@example.test\nNew Marine Supplier,new@example.test\n"
        );

        $this->actingAs($admin)
            ->post(route('admin.onboarding.bulk-import.store'), [
                'audience' => 'seller',
                'file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Import completed. New accounts created: 1. Existing onboarding records updated: 0. Completion emails queued: 1. Emails will be sent one by one every 2 minutes. Existing platform accounts skipped: 1. Duplicate rows skipped: 0. Invalid rows skipped: 0.');

        $this->assertSame(1, User::query()->where('email', 'old@example.test')->count());
        $newUser = User::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('New Marine Supplier', $newUser->company_name);

        $this->assertDatabaseMissing('outreach_contacts', ['email' => 'old@example.test']);
        $this->assertDatabaseHas('outreach_contacts', ['email' => 'new@example.test']);

        Queue::assertPushed(SendOnboardingCompletionEmail::class, 1);
        Notification::assertNothingSent();
    }

    public function test_bulk_import_with_only_existing_accounts_returns_clean_skip_message(): void
    {
        Notification::fake();
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'role' => 'seller',
            'email' => 'old@example.test',
            'company_name' => 'Old Marine Supplier',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'companies.csv',
            "Company Name,Email\nOld Marine Supplier,old@example.test\n"
        );

        $this->actingAs($admin)
            ->post(route('admin.onboarding.bulk-import.store'), [
                'audience' => 'seller',
                'file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Import completed. No new accounts were created because all valid rows were already in the system. Existing accounts skipped: 1. Duplicate rows skipped: 0. Invalid rows skipped: 0.');

        $this->assertSame(1, User::query()->where('email', 'old@example.test')->count());
        $this->assertDatabaseMissing('outreach_contacts', ['email' => 'old@example.test']);
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_queued_completion_email_job_sends_notification_and_marks_record_sent(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => 'seller',
            'email' => 'queued@example.test',
            'company_name' => 'Queued Marine Ltd',
            'email_verified_at' => null,
            'seller_verification_onboarding_sent_at' => null,
        ]);

        $contact = OutreachContact::query()->create([
            'email' => 'queued@example.test',
            'audience' => 'seller',
            'organization_name' => 'Queued Marine Ltd',
            'source_name' => 'Bulk company import',
            'status' => OutreachContact::STATUS_REGISTERED,
            'source_payload' => [
                'created_from' => 'bulk_company_import',
                'onboarding_status' => 'email_queued',
                'user_id' => $user->id,
            ],
        ]);

        app()->call([new SendOnboardingCompletionEmail($contact->id), 'handle']);

        $user->refresh();
        $contact->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->seller_verification_onboarding_sent_at);
        $this->assertSame('email_sent', data_get($contact->source_payload, 'onboarding_status'));
        $this->assertSame('completion_email_sent', $contact->last_result);

        Notification::assertSentTo($user, PreRegisteredAccountCompletionNotification::class);
    }

    public function test_expired_bulk_imported_supplier_is_deleted_if_registration_is_not_completed(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => 'seller',
            'email' => 'expired@example.test',
            'company_name' => 'Expired Marine Ltd',
            'email_verified_at' => now()->subDays(15),
            'approval_status' => 'rejected',
            'seller_verification_submitted_at' => null,
            'seller_verification_onboarding_sent_at' => now()->subDays(15),
            'seller_verification_24h_reminder_sent_at' => now()->subDays(14),
            'seller_verification_72h_reminder_sent_at' => now()->subDays(12),
        ]);

        OutreachContact::query()->create([
            'email' => 'expired@example.test',
            'audience' => 'seller',
            'organization_name' => 'Expired Marine Ltd',
            'source_name' => 'Bulk company import',
            'status' => OutreachContact::STATUS_REGISTERED,
            'source_payload' => [
                'created_from' => 'bulk_company_import',
                'onboarding_status' => 'email_sent',
                'user_id' => $user->id,
                'parsed' => [
                    'company_name' => 'Expired Marine Ltd',
                    'email' => 'expired@example.test',
                ],
                'bulk_import_expires_at' => now()->subDay()->toIso8601String(),
            ],
        ]);

        $this->artisan('onboarding:delete-expired-imports')
            ->expectsOutput('Expired onboarding imports processed. deleted=1, skipped_completed=0, notified=1, notification_failed=0')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['email' => 'expired@example.test']);
        $this->assertDatabaseMissing('outreach_contacts', ['email' => 'expired@example.test']);
    }
    public function test_non_admin_cannot_open_onboarding_workspace(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);

        $this->actingAs($buyer)
            ->get(route('admin.onboarding'))
            ->assertForbidden();
    }
}
