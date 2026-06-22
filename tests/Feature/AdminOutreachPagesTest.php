<?php

namespace Tests\Feature;

use App\Jobs\ImportOutreachContactsJob;
use App\Mail\OutreachSenderTestMail;
use App\Models\OutreachContact;
use App\Models\OutreachImportRun;
use App\Models\OutreachSchedule;
use App\Models\OutreachSegment;
use App\Models\OutreachSendLog;
use App\Models\OutreachSenderAccount;
use App\Models\OutreachTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminOutreachPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_outreach_workspace(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $segment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER EUROPE-1',
            'audience' => 'supplier',
            'region_key' => 'europe',
            'recommended_weekday' => 2,
            'recommended_start_time' => '13:00',
            'recommended_end_time' => '15:00',
            'is_active' => true,
        ]);

        $regionSegment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER EUROPE',
            'audience' => 'supplier',
            'region_key' => 'europe',
            'recommended_weekday' => 2,
            'recommended_start_time' => '13:00',
            'recommended_end_time' => '15:00',
            'is_active' => true,
        ]);

        $template = OutreachTemplate::query()->create([
            'audience' => 'supplier',
            'name' => 'Supplier Intro 01',
            'subject' => 'Explore Sea Requests',
            'body_text' => 'Hello {{company_name}}, visit {{site_url}} and unsubscribe via {{unsubscribe_url}} if needed.',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $admin->id,
        ]);

        OutreachSenderAccount::query()->create([
            'audience' => 'supplier',
            'name' => 'Primary Request Mailbox',
            'from_name' => 'Sea Requests',
            'from_email' => 'request@example.com',
            'reply_to_email' => 'support@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'request@example.com',
            'smtp_password' => 'secret-pass',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $admin->id,
        ]);

        $contact = OutreachContact::query()->create([
            'email' => 'supplier@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $regionSegment->id,
            'organization_name' => 'Supplier Company',
            'status' => OutreachContact::STATUS_ACTIVE,
        ]);

        $contact->segments()->syncWithoutDetaching([$segment->id, $regionSegment->id]);

        $schedule = OutreachSchedule::query()->create([
            'segment_id' => $regionSegment->id,
            'audience' => 'supplier',
            'recurrence' => 'weekly',
            'starts_on' => now()->toDateString(),
            'weekday' => 2,
            'suggested_start_time' => '13:00',
            'suggested_end_time' => '15:00',
            'uses_recommended_window' => true,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'send_interval_minutes' => 5,
            'is_active' => true,
            'template_rotation' => [],
        ]);

        OutreachSendLog::query()->create([
            'contact_id' => $contact->id,
            'segment_id' => $regionSegment->id,
            'schedule_id' => $schedule->id,
            'template_id' => $template->id,
            'cycle_key' => 'weekly-2026-06-15',
            'recipient_email' => $contact->email,
            'recipient_organization' => $contact->organization_name,
            'status' => OutreachSendLog::STATUS_QUEUED,
            'subject' => 'Explore Sea Requests',
            'queued_at' => now(),
        ]);

        OutreachImportRun::query()->create([
            'audience' => 'supplier',
            'file_name' => 'suppliers.csv',
            'stored_path' => 'outreach-imports/suppliers.csv',
            'status' => 'completed',
            'imported_by' => $admin->id,
            'row_count' => 10,
            'processed_count' => 8,
            'new_contacts_count' => 6,
            'updated_contacts_count' => 2,
            'duplicate_emails_count' => 1,
            'skipped_count' => 2,
            'completed_at' => now(),
            'message' => 'Completed.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.outreach'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Outreach/Index')
                ->where('activeTab', 'outreach')
                ->where('dashboard.navigation.outreach_count', 1)
                ->where('summary.total_contacts', 1)
                ->where('summary.active_contacts', 1)
                ->where('senderAccounts.0.name', 'Primary Request Mailbox')
                ->where('templates.0.name', 'Supplier Intro 01')
                ->where('regions.0.label', 'Europe')
                ->where('regions.0.active_contacts', 1)
                ->where('contactsPage.data.0.email', 'supplier@example.com')
                ->where('contactsPage.data.0.status', 'active')
                ->where('contactsPage.data.0.delete_url', route('admin.outreach.contacts.destroy', $contact))
                ->where('logs.0.recipient_email', 'supplier@example.com')
                ->where('imports.0.file_name', 'suppliers.csv')
            );
    }

    public function test_admin_can_filter_outreach_contacts_by_status_region_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $asiaSegment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER ASIA',
            'audience' => 'supplier',
            'region_key' => 'asia',
            'recommended_weekday' => 1,
            'recommended_start_time' => '09:00',
            'recommended_end_time' => '11:00',
            'is_active' => true,
        ]);

        $europeSegment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER EUROPE',
            'audience' => 'supplier',
            'region_key' => 'europe',
            'recommended_weekday' => 2,
            'recommended_start_time' => '13:00',
            'recommended_end_time' => '15:00',
            'is_active' => true,
        ]);

        OutreachContact::query()->create([
            'email' => 'registered-asia@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $asiaSegment->id,
            'organization_name' => 'Asia Registered Marine',
            'status' => OutreachContact::STATUS_REGISTERED,
        ]);

        OutreachContact::query()->create([
            'email' => 'active-asia@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $asiaSegment->id,
            'organization_name' => 'Asia Active Marine',
            'status' => OutreachContact::STATUS_ACTIVE,
        ]);

        OutreachContact::query()->create([
            'email' => 'registered-europe@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $europeSegment->id,
            'organization_name' => 'Europe Registered Marine',
            'status' => OutreachContact::STATUS_REGISTERED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.outreach', [
                'contacts_status' => 'registered',
                'contacts_region' => 'asia',
                'contacts_search' => 'Asia Registered',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Outreach/Index')
                ->where('contactsPage.filters.status', 'registered')
                ->where('contactsPage.filters.region', 'asia')
                ->where('contactsPage.filters.search', 'Asia Registered')
                ->where('contactsPage.meta.total', 1)
                ->where('contactsPage.data.0.email', 'registered-asia@example.com')
                ->where('contactsPage.data.0.region_key', 'asia')
                ->where('contactsPage.data.0.status', 'registered')
            );
    }

    public function test_non_admin_users_cannot_open_outreach_workspace(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($buyer)->get(route('admin.outreach'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.outreach'))->assertForbidden();
    }

    public function test_admin_can_queue_supplier_import_from_csv(): void
    {
        Queue::fake();
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "Labels,Organization Name,E-mail 1 - Value\nSUPPLIER EUROPE-1 ::: * myContacts,*,Acme Marine,acme@example.com\n"
        );

        $this->actingAs($admin)
            ->post(route('admin.outreach.imports.store'), [
                'file' => $file,
            ])
            ->assertRedirect();

        Queue::assertPushed(ImportOutreachContactsJob::class);

        $this->assertDatabaseHas('outreach_import_runs', [
            'audience' => 'supplier',
            'file_name' => 'contacts.csv',
            'status' => 'queued',
        ]);
    }

    public function test_supplier_csv_import_processes_contacts_successfully(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'role' => 'seller',
            'email' => 'registered-supplier@example.com',
        ]);

        Storage::disk('local')->put(
            'outreach-imports/suppliers.csv',
            implode("\n", [
                'Labels,Organization Name,E-mail 1 - Value,First Name,Last Name,File As',
                'SUPPLIER EUROPE-1 ::: * myContacts,Acme Marine,active-supplier@example.com,Andrew,Stone,Acme Marine',
                'SUPPLIER EUROPE-1 ::: * myContacts,Registered Marine,registered-supplier@example.com,Sarah,North,Registered Marine',
            ])
        );

        $run = OutreachImportRun::query()->create([
            'audience' => 'supplier',
            'file_name' => 'suppliers.csv',
            'stored_path' => 'outreach-imports/suppliers.csv',
            'status' => 'queued',
            'imported_by' => $admin->id,
            'message' => 'Queued for import.',
        ]);

        app(\App\Support\Outreach\OutreachCsvImporter::class)->import($run);

        $run->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->row_count);
        $this->assertSame(2, $run->processed_count);
        $this->assertSame(2, $run->new_contacts_count);
        $this->assertSame(0, $run->updated_contacts_count);
        $this->assertSame(0, $run->duplicate_emails_count);
        $this->assertSame(0, $run->skipped_count);

        $regionSegment = OutreachSegment::query()->where('name', 'SUPPLIER EUROPE')->firstOrFail();
        $sourceSegment = OutreachSegment::query()->where('name', 'SUPPLIER EUROPE-1')->firstOrFail();

        $activeContact = OutreachContact::query()->where('email', 'active-supplier@example.com')->firstOrFail();
        $registeredContact = OutreachContact::query()->where('email', 'registered-supplier@example.com')->firstOrFail();

        $this->assertSame($regionSegment->id, $activeContact->primary_segment_id);
        $this->assertSame(OutreachContact::STATUS_ACTIVE, $activeContact->status);
        $this->assertSame('europe', $activeContact->source_payload['region_key'] ?? null);
        $this->assertSame('SUPPLIER EUROPE-1', $activeContact->source_payload['raw_label'] ?? null);

        $this->assertSame($regionSegment->id, $registeredContact->primary_segment_id);
        $this->assertSame(OutreachContact::STATUS_REGISTERED, $registeredContact->status);

        $this->assertDatabaseHas('outreach_contact_segment', [
            'outreach_contact_id' => $activeContact->id,
            'outreach_segment_id' => $regionSegment->id,
        ]);

        $this->assertDatabaseHas('outreach_contact_segment', [
            'outreach_contact_id' => $activeContact->id,
            'outreach_segment_id' => $sourceSegment->id,
        ]);
    }

    public function test_admin_can_add_manual_supplier_contact_to_region(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.outreach.contacts.store'), [
                'email' => 'manual-supplier@example.com',
                'organization_name' => 'Manual Supplier Co',
                'source_name' => 'Andrew',
                'region_key' => 'asia',
                'notes' => 'Found from a direct recommendation.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outreach_contacts', [
            'email' => 'manual-supplier@example.com',
            'organization_name' => 'Manual Supplier Co',
            'status' => OutreachContact::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('outreach_segments', [
            'name' => 'SUPPLIER ASIA',
            'audience' => 'supplier',
            'region_key' => 'asia',
        ]);

        $contact = OutreachContact::query()->where('email', 'manual-supplier@example.com')->firstOrFail();
        $segment = OutreachSegment::query()->where('name', 'SUPPLIER ASIA')->firstOrFail();

        $this->assertSame($segment->id, $contact->primary_segment_id);
    }

    public function test_admin_cannot_add_manual_supplier_contact_without_required_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.outreach'))
            ->post(route('admin.outreach.contacts.store'), [
                'email' => '',
                'organization_name' => '',
                'region_key' => '',
            ])
            ->assertRedirect(route('admin.outreach'))
            ->assertSessionHasErrors(['email', 'organization_name', 'region_key']);

        $this->assertDatabaseCount('outreach_contacts', 0);
    }

    public function test_admin_can_delete_outreach_contact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $segment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER ASIA',
            'audience' => 'supplier',
            'region_key' => 'asia',
            'recommended_weekday' => 1,
            'recommended_start_time' => '09:00',
            'recommended_end_time' => '11:00',
            'is_active' => true,
        ]);

        $contact = OutreachContact::query()->create([
            'email' => 'delete-me@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $segment->id,
            'organization_name' => 'Delete Me Marine',
            'status' => OutreachContact::STATUS_ACTIVE,
        ]);

        $schedule = OutreachSchedule::query()->create([
            'segment_id' => $segment->id,
            'audience' => 'supplier',
            'recurrence' => 'weekly',
            'starts_on' => now()->toDateString(),
            'weekday' => 1,
            'suggested_start_time' => '09:00',
            'suggested_end_time' => '11:00',
            'uses_recommended_window' => true,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'send_interval_minutes' => 1,
            'is_active' => true,
            'template_rotation' => [],
        ]);

        OutreachSendLog::query()->create([
            'contact_id' => $contact->id,
            'segment_id' => $segment->id,
            'schedule_id' => $schedule->id,
            'cycle_key' => 'weekly-delete-test',
            'recipient_email' => $contact->email,
            'recipient_organization' => $contact->organization_name,
            'status' => OutreachSendLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.outreach.contacts.destroy', $contact))
            ->assertRedirect();

        $this->assertDatabaseMissing('outreach_contacts', [
            'id' => $contact->id,
        ]);

        $this->assertDatabaseCount('outreach_send_logs', 0);
    }

    public function test_admin_can_reactivate_unsubscribed_outreach_contact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $segment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER ASIA',
            'audience' => 'supplier',
            'region_key' => 'asia',
            'recommended_weekday' => 1,
            'recommended_start_time' => '09:00',
            'recommended_end_time' => '11:00',
            'is_active' => true,
        ]);

        $contact = OutreachContact::query()->create([
            'email' => 'reactivate@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $segment->id,
            'organization_name' => 'Reactivation Marine',
            'status' => OutreachContact::STATUS_UNSUBSCRIBED,
            'last_result' => 'unsubscribed',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.outreach.contacts.reactivate', $contact))
            ->assertRedirect();

        $this->assertDatabaseHas('outreach_contacts', [
            'id' => $contact->id,
            'status' => OutreachContact::STATUS_ACTIVE,
            'last_result' => 'reactivated_by_admin',
        ]);
    }

    public function test_reactivated_outreach_contact_returns_to_registered_when_matching_seller_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'role' => 'seller',
            'email' => 'registered-reactivate@example.com',
        ]);

        $segment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER EUROPE',
            'audience' => 'supplier',
            'region_key' => 'europe',
            'recommended_weekday' => 2,
            'recommended_start_time' => '13:00',
            'recommended_end_time' => '15:00',
            'is_active' => true,
        ]);

        $contact = OutreachContact::query()->create([
            'email' => 'registered-reactivate@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $segment->id,
            'organization_name' => 'Registered Reactivation Marine',
            'status' => OutreachContact::STATUS_UNSUBSCRIBED,
            'last_result' => 'unsubscribed',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.outreach.contacts.reactivate', $contact))
            ->assertRedirect();

        $this->assertDatabaseHas('outreach_contacts', [
            'id' => $contact->id,
            'status' => OutreachContact::STATUS_REGISTERED,
            'last_result' => 'reactivated_by_admin',
        ]);
    }

    public function test_admin_can_create_sender_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.outreach.senders.store'), [
                'from_name' => 'Sea Requests',
                'from_email' => 'request@example.com',
                'smtp_host' => 'smtp.googlemail.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => 'request@example.com',
                'smtp_password' => 'secret-pass',
                'is_active' => true,
                'is_default' => true,
            ])
            ->assertRedirect();

        $sender = OutreachSenderAccount::query()->firstOrFail();

        $this->assertSame('request@example.com', $sender->name);
        $this->assertSame('request@example.com', $sender->reply_to_email);
        $this->assertSame('secret-pass', $sender->smtp_password);
        $this->assertTrue($sender->is_default);
    }

    public function test_admin_can_update_sender_account_without_overwriting_saved_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $sender = OutreachSenderAccount::query()->create([
            'audience' => 'supplier',
            'name' => 'Primary Request Mailbox',
            'from_name' => 'Sea Requests',
            'from_email' => 'request@example.com',
            'reply_to_email' => 'support@example.com',
            'smtp_host' => 'smtp.googlemail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'request@example.com',
            'smtp_password' => 'secret-pass',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.outreach.senders.update', $sender), [
                'from_name' => 'Sea Requests Team',
                'from_email' => 'updated-request@example.com',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'smtp_username' => 'updated-request@example.com',
                'smtp_password' => '',
                'is_active' => true,
                'is_default' => true,
            ])
            ->assertRedirect();

        $sender->refresh();

        $this->assertSame('updated-request@example.com', $sender->name);
        $this->assertSame('Sea Requests Team', $sender->from_name);
        $this->assertSame('updated-request@example.com', $sender->from_email);
        $this->assertSame('updated-request@example.com', $sender->reply_to_email);
        $this->assertSame('smtp.gmail.com', $sender->smtp_host);
        $this->assertSame(465, $sender->smtp_port);
        $this->assertSame('ssl', $sender->smtp_encryption);
        $this->assertSame('secret-pass', $sender->smtp_password);
    }

    public function test_admin_can_send_test_email_for_draft_sender_settings(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.outreach.senders.test-draft'), [
                'from_name' => 'Sea Requests',
                'from_email' => 'request@example.com',
                'smtp_host' => 'smtp.googlemail.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => 'request@example.com',
                'smtp_password' => 'secret-pass',
                'is_active' => true,
                'is_default' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success.message', 'Test email sent to request@example.com.');

        Mail::assertSent(OutreachSenderTestMail::class, function (OutreachSenderTestMail $mail) {
            return $mail->hasTo('request@example.com')
                && $mail->fromEmail === 'request@example.com'
                && $mail->fromName === 'Sea Requests'
                && $mail->replyToEmail === 'request@example.com';
        });
    }

    public function test_admin_can_send_test_email_for_saved_sender_account(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $sender = OutreachSenderAccount::query()->create([
            'audience' => 'supplier',
            'name' => 'Primary Request Mailbox',
            'from_name' => 'Sea Requests',
            'from_email' => 'request@example.com',
            'reply_to_email' => 'support@example.com',
            'smtp_host' => 'smtp.googlemail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'request@example.com',
            'smtp_password' => 'secret-pass',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.outreach.senders.test', $sender))
            ->assertRedirect()
            ->assertSessionHas('success.message', 'Test email sent to request@example.com.');

        Mail::assertSent(OutreachSenderTestMail::class, function (OutreachSenderTestMail $mail) use ($sender) {
            return $mail->hasTo($sender->from_email)
                && $mail->fromEmail === $sender->from_email
                && $mail->fromName === $sender->from_name
                && $mail->replyToEmail === $sender->reply_to_email;
        });
    }

    public function test_admin_cannot_delete_sender_account_that_still_has_queued_emails(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $sender = OutreachSenderAccount::query()->create([
            'audience' => 'supplier',
            'name' => 'Primary Request Mailbox',
            'from_name' => 'Sea Requests',
            'from_email' => 'request@example.com',
            'reply_to_email' => 'support@example.com',
            'smtp_host' => 'smtp.googlemail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'request@example.com',
            'smtp_password' => 'secret-pass',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $admin->id,
        ]);

        $segment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER ASIA',
            'audience' => 'supplier',
            'region_key' => 'asia',
            'recommended_weekday' => 1,
            'recommended_start_time' => '09:00',
            'recommended_end_time' => '11:00',
            'is_active' => true,
        ]);

        $contact = OutreachContact::query()->create([
            'email' => 'manual-supplier@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $segment->id,
            'organization_name' => 'Manual Supplier Co',
            'status' => OutreachContact::STATUS_ACTIVE,
        ]);

        $template = OutreachTemplate::query()->create([
            'audience' => 'supplier',
            'name' => 'Supplier Intro 03',
            'subject' => 'Explore Sea Requests',
            'body_text' => 'Simple outreach body text.',
            'is_active' => true,
            'sort_order' => 3,
            'created_by' => $admin->id,
        ]);

        $schedule = OutreachSchedule::query()->create([
            'segment_id' => $segment->id,
            'audience' => 'supplier',
            'recurrence' => 'weekly',
            'starts_on' => now()->toDateString(),
            'weekday' => 1,
            'suggested_start_time' => '09:00',
            'suggested_end_time' => '11:00',
            'uses_recommended_window' => true,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'send_interval_minutes' => 5,
            'is_active' => true,
            'template_rotation' => [],
        ]);

        OutreachSendLog::query()->create([
            'contact_id' => $contact->id,
            'segment_id' => $segment->id,
            'schedule_id' => $schedule->id,
            'template_id' => $template->id,
            'sender_account_id' => $sender->id,
            'cycle_key' => 'daily-2026-06-21',
            'recipient_email' => $contact->email,
            'recipient_organization' => $contact->organization_name,
            'sender_email' => $sender->from_email,
            'status' => OutreachSendLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.outreach.senders.destroy', $sender))
            ->assertSessionHasErrors('sender');

        $this->assertDatabaseHas('outreach_sender_accounts', [
            'id' => $sender->id,
        ]);
    }

    public function test_admin_can_delete_unused_supplier_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $template = OutreachTemplate::query()->create([
            'audience' => 'supplier',
            'name' => 'Supplier Intro 02',
            'subject' => 'Explore Sea Requests',
            'body_text' => 'Simple outreach body text.',
            'is_active' => true,
            'sort_order' => 2,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.outreach.templates.destroy', $template))
            ->assertRedirect();

        $this->assertDatabaseMissing('outreach_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_admin_can_delete_template_even_if_old_region_plan_reference_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $segment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER ASIA',
            'audience' => 'supplier',
            'region_key' => 'asia',
            'recommended_weekday' => 1,
            'recommended_start_time' => '09:00',
            'recommended_end_time' => '11:00',
            'is_active' => true,
        ]);

        $template = OutreachTemplate::query()->create([
            'audience' => 'supplier',
            'name' => 'Supplier Intro 03',
            'subject' => 'Explore Sea Requests',
            'body_text' => 'Simple outreach body text.',
            'is_active' => true,
            'sort_order' => 3,
            'created_by' => $admin->id,
        ]);

        OutreachSchedule::query()->create([
            'segment_id' => $segment->id,
            'audience' => 'supplier',
            'recurrence' => 'weekly',
            'starts_on' => now()->toDateString(),
            'weekday' => 1,
            'suggested_start_time' => '09:00',
            'suggested_end_time' => '11:00',
            'uses_recommended_window' => true,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'send_interval_minutes' => 5,
            'is_active' => true,
            'template_rotation' => [$template->id],
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.outreach.templates.destroy', $template))
            ->assertRedirect();

        $this->assertDatabaseMissing('outreach_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_signed_unsubscribe_link_shows_confirmation_before_status_change(): void
    {
        $contact = OutreachContact::query()->create([
            'email' => 'unsubscribe@example.com',
            'audience' => 'supplier',
            'status' => OutreachContact::STATUS_ACTIVE,
        ]);

        $url = URL::signedRoute('outreach.unsubscribe', ['contact' => $contact->id]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Confirm your unsubscribe request.', false)
            ->assertSee('unsubscribe@example.com', false);

        $this->assertDatabaseHas('outreach_contacts', [
            'id' => $contact->id,
            'status' => OutreachContact::STATUS_ACTIVE,
        ]);
    }

    public function test_signed_unsubscribe_confirmation_post_marks_contact_as_unsubscribed(): void
    {
        $contact = OutreachContact::query()->create([
            'email' => 'unsubscribe@example.com',
            'audience' => 'supplier',
            'status' => OutreachContact::STATUS_ACTIVE,
        ]);

        $url = URL::signedRoute('outreach.unsubscribe', ['contact' => $contact->id]);

        $this->post($url)
            ->assertOk()
            ->assertSee('You have been unsubscribed.', false)
            ->assertSee('unsubscribe@example.com', false);

        $this->assertDatabaseHas('outreach_contacts', [
            'id' => $contact->id,
            'status' => OutreachContact::STATUS_UNSUBSCRIBED,
            'last_result' => 'unsubscribed',
        ]);
    }
}
