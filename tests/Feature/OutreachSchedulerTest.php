<?php

namespace Tests\Feature;

use App\Jobs\SendOutreachEmailJob;
use App\Models\OutreachContact;
use App\Models\OutreachSchedule;
use App\Models\OutreachSegment;
use App\Models\OutreachSendLog;
use App\Models\OutreachTemplate;
use App\Support\Outreach\OutreachScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OutreachSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_rotates_active_templates_globally_by_sort_order(): void
    {
        Queue::fake();

        $segment = OutreachSegment::query()->create([
            'name' => 'SUPPLIER EUROPE',
            'audience' => 'supplier',
            'region_key' => 'europe',
            'recommended_weekday' => 1,
            'recommended_start_time' => '09:00',
            'recommended_end_time' => '11:00',
            'is_active' => true,
        ]);

        $firstTemplate = OutreachTemplate::query()->create([
            'audience' => 'supplier',
            'name' => 'Template A',
            'subject' => 'Subject A',
            'body_text' => 'Body A',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $secondTemplate = OutreachTemplate::query()->create([
            'audience' => 'supplier',
            'name' => 'Template B',
            'subject' => 'Subject B',
            'body_text' => 'Body B',
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $alreadyQueuedContact = OutreachContact::query()->create([
            'email' => 'already-queued@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $segment->id,
            'organization_name' => 'Already Queued Supplier',
            'status' => OutreachContact::STATUS_ACTIVE,
            'last_sent_at' => CarbonImmutable::parse('2026-06-21 09:01:00'),
        ]);

        $contact = OutreachContact::query()->create([
            'email' => 'rotation@example.com',
            'audience' => 'supplier',
            'primary_segment_id' => $segment->id,
            'organization_name' => 'Rotation Supplier',
            'status' => OutreachContact::STATUS_ACTIVE,
        ]);

        $schedule = OutreachSchedule::query()->create([
            'segment_id' => $segment->id,
            'audience' => 'supplier',
            'recurrence' => 'weekly',
            'starts_on' => '2026-06-22',
            'weekday' => 1,
            'suggested_start_time' => '09:00',
            'suggested_end_time' => '11:00',
            'uses_recommended_window' => false,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'send_interval_minutes' => 1,
            'is_active' => true,
            'template_rotation' => [],
        ]);

        OutreachSendLog::query()->create([
            'contact_id' => $alreadyQueuedContact->id,
            'segment_id' => $segment->id,
            'schedule_id' => $schedule->id,
            'template_id' => $firstTemplate->id,
            'cycle_key' => 'daily-2026-06-21',
            'recipient_email' => $alreadyQueuedContact->email,
            'recipient_organization' => $alreadyQueuedContact->organization_name,
            'status' => OutreachSendLog::STATUS_SENT,
            'queued_at' => CarbonImmutable::parse('2026-06-21 09:00:00'),
            'attempted_at' => CarbonImmutable::parse('2026-06-21 09:00:30'),
            'sent_at' => CarbonImmutable::parse('2026-06-21 09:01:00'),
        ]);

        $scheduler = app(OutreachScheduler::class);
        $now = CarbonImmutable::parse('2026-06-22 10:00:00');

        $this->assertTrue($scheduler->dispatchSchedule($schedule, $now));

        $log = OutreachSendLog::query()->latest('id')->firstOrFail();

        $this->assertSame($contact->id, $log->contact_id);
        $this->assertSame($secondTemplate->id, $log->template_id);

        Queue::assertPushed(SendOutreachEmailJob::class);
    }
}
