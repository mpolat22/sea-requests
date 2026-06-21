<?php

namespace Tests\Feature;

use App\Jobs\SendOutreachEmailJob;
use App\Mail\OutreachPlainTextMail;
use App\Models\OutreachContact;
use App\Models\OutreachSchedule;
use App\Models\OutreachSegment;
use App\Models\OutreachSendLog;
use App\Models\OutreachSenderAccount;
use App\Models\OutreachTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendOutreachEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_uses_assigned_sender_account_details(): void
    {
        Mail::fake();

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
            'name' => 'SUPPLIER EUROPE',
            'audience' => 'supplier',
            'region_key' => 'europe',
            'recommended_weekday' => 1,
            'recommended_start_time' => '09:00',
            'recommended_end_time' => '11:00',
            'is_active' => true,
        ]);

        $template = OutreachTemplate::query()->create([
            'audience' => 'supplier',
            'name' => 'Supplier Intro 01',
            'subject' => 'Explore {{app_name}}',
            'body_text' => 'Hello {{company_name}}, visit {{site_url}}.',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $admin->id,
        ]);

        $contact = OutreachContact::query()->create([
            'email' => 'supplier@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $segment->id,
            'organization_name' => 'Supplier Company',
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
            'send_interval_minutes' => 5,
            'is_active' => true,
            'template_rotation' => [],
        ]);

        $log = OutreachSendLog::query()->create([
            'contact_id' => $contact->id,
            'segment_id' => $segment->id,
            'schedule_id' => $schedule->id,
            'template_id' => $template->id,
            'sender_account_id' => $sender->id,
            'cycle_key' => 'daily-2026-06-21',
            'recipient_email' => $contact->email,
            'recipient_organization' => $contact->organization_name,
            'status' => OutreachSendLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        app(SendOutreachEmailJob::class, ['sendLogId' => $log->id])->handle(
            app(\App\Support\Outreach\OutreachTemplateRenderer::class),
            app(\App\Support\Outreach\OutreachScheduler::class),
            app(\App\Support\Outreach\OutreachSenderAccountManager::class),
            app(\App\Support\Outreach\OutreachDynamicMailer::class),
        );

        $log->refresh();

        $this->assertSame(OutreachSendLog::STATUS_SENT, $log->status);
        $this->assertSame('request@example.com', $log->sender_email);

        Mail::assertSent(OutreachPlainTextMail::class, function (OutreachPlainTextMail $mail) use ($sender) {
            return $mail->fromEmail === $sender->from_email
                && $mail->fromName === $sender->from_name
                && $mail->replyToEmail === $sender->reply_to_email;
        });
    }
}
