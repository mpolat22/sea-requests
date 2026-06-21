<?php

namespace App\Support\Outreach;

use App\Jobs\SendOutreachEmailJob;
use App\Models\OutreachContact;
use App\Models\OutreachSchedule;
use App\Models\OutreachSendLog;
use App\Models\OutreachTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OutreachScheduler
{
    public function __construct(
        private OutreachSenderAccountManager $senderAccounts
    ) {}

    public function dueSchedules(CarbonImmutable $now): Collection
    {
        return OutreachSchedule::query()
            ->with(['segment'])
            ->where('is_active', true)
            ->whereHas('segment', fn ($query) => $query->where('is_active', true))
            ->get()
            ->filter(fn (OutreachSchedule $schedule) => $this->isInActiveWindow($schedule, $now));
    }

    public function dispatchSchedule(OutreachSchedule $schedule, CarbonImmutable $now): bool
    {
        if (! $this->canDispatchNow($schedule, $now)) {
            return false;
        }

        $cycleKey = $this->cycleKey($schedule, $now);

        if ($cycleKey === null) {
            return false;
        }

        $contact = $this->nextContactForSchedule($schedule, $cycleKey);

        if (! $contact) {
            return false;
        }

        $templatePool = OutreachTemplate::query()
            ->where('audience', $schedule->audience)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($templatePool->isEmpty()) {
            return false;
        }

        $senderAccount = $this->senderAccounts->resolveForAudience($schedule->audience);

        if (! $senderAccount && $this->hasConfiguredButInactiveSenderAccounts($schedule->audience)) {
            return false;
        }

        $rotationPosition = OutreachSendLog::query()
            ->whereNotNull('template_id')
            ->whereIn('status', [
                OutreachSendLog::STATUS_QUEUED,
                OutreachSendLog::STATUS_SENT,
                OutreachSendLog::STATUS_FAILED,
                OutreachSendLog::STATUS_SKIPPED,
            ])
            ->whereHas('schedule', fn ($query) => $query->where('audience', $schedule->audience))
            ->count();

        $templateId = $templatePool[$rotationPosition % $templatePool->count()];

        $log = OutreachSendLog::query()->create([
            'contact_id' => $contact->id,
            'segment_id' => $schedule->segment_id,
            'schedule_id' => $schedule->id,
            'template_id' => $templateId,
            'sender_account_id' => $senderAccount?->id,
            'cycle_key' => $cycleKey,
            'recipient_email' => $contact->email,
            'recipient_organization' => $contact->organization_name,
            'sender_email' => $senderAccount?->from_email ?: (string) config('mail.from.address'),
            'status' => OutreachSendLog::STATUS_QUEUED,
            'queued_at' => $now,
        ]);

        $schedule->forceFill([
            'last_dispatched_at' => $now,
            'last_cycle_key' => $cycleKey,
        ])->save();

        SendOutreachEmailJob::dispatch($log->id)->onQueue('outreach');

        return true;
    }

    public function cycleKey(OutreachSchedule $schedule, CarbonImmutable $now): ?string
    {
        if (! $this->isDispatchWeek($schedule, $now)) {
            return null;
        }

        $dayPlan = $this->dayPlan($schedule, (int) $now->isoWeekday());

        if (! $dayPlan || ! ($dayPlan['enabled'] ?? false)) {
            return null;
        }

        return 'daily-'.$now->toDateString();
    }

    public function isInActiveWindow(OutreachSchedule $schedule, CarbonImmutable $now): bool
    {
        $cycleKey = $this->cycleKey($schedule, $now);

        if ($cycleKey === null) {
            return false;
        }

        $dayPlan = $this->dayPlan($schedule, (int) $now->isoWeekday());

        if (! $dayPlan) {
            return false;
        }

        $currentTime = $now->format('H:i');

        return $currentTime >= ($dayPlan['start_time'] ?? '00:00')
            && $currentTime <= ($dayPlan['end_time'] ?? '23:59');
    }

    public function canDispatchNow(OutreachSchedule $schedule, CarbonImmutable $now): bool
    {
        if (! $this->isInActiveWindow($schedule, $now)) {
            return false;
        }

        $cycleKey = $this->cycleKey($schedule, $now);

        if ($cycleKey === null || ! $this->hasCapacityForCycle($schedule, $now, $cycleKey)) {
            return false;
        }

        if ($schedule->last_dispatched_at === null) {
            return true;
        }

        return $schedule->last_dispatched_at->diffInMinutes($now) >= max(1, (int) $schedule->send_interval_minutes);
    }

    public function nextContactForSchedule(OutreachSchedule $schedule, string $cycleKey): ?OutreachContact
    {
        return OutreachContact::query()
            ->eligible()
            ->where('audience', $schedule->audience)
            ->where('primary_segment_id', $schedule->segment_id)
            ->whereNotExists(function ($query) use ($schedule, $cycleKey) {
                $query->selectRaw('1')
                    ->from('outreach_send_logs')
                    ->whereColumn('outreach_send_logs.contact_id', 'outreach_contacts.id')
                    ->where('outreach_send_logs.schedule_id', $schedule->id)
                    ->where('outreach_send_logs.cycle_key', $cycleKey)
                    ->whereIn('outreach_send_logs.status', [
                        OutreachSendLog::STATUS_QUEUED,
                        OutreachSendLog::STATUS_SENT,
                    ]);
            })
            ->orderByRaw('case when last_sent_at is null then 0 else 1 end')
            ->orderBy('last_sent_at')
            ->orderBy('id')
            ->first();
    }

    public function refreshRegisteredState(OutreachContact $contact): bool
    {
        $isRegistered = User::query()
            ->where('role', 'seller')
            ->whereRaw('lower(email) = ?', [mb_strtolower($contact->email)])
            ->exists();

        if ($isRegistered && $contact->status !== OutreachContact::STATUS_REGISTERED) {
            $contact->forceFill([
                'status' => OutreachContact::STATUS_REGISTERED,
                'last_result' => 'Registered seller detected.',
            ])->save();
        }

        return $isRegistered;
    }

    private function dayPlan(OutreachSchedule $schedule, int $weekday): ?array
    {
        $meta = is_array($schedule->meta) ? $schedule->meta : [];
        $weeklyPlan = collect($meta['weekly_plan'] ?? [])
            ->first(fn ($day) => (int) ($day['weekday'] ?? 0) === $weekday);

        if (is_array($weeklyPlan)) {
            return $weeklyPlan;
        }

        if ((int) $schedule->weekday !== $weekday) {
            return null;
        }

        return [
            'weekday' => $weekday,
            'enabled' => true,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'daily_limit' => null,
        ];
    }

    private function hasCapacityForCycle(OutreachSchedule $schedule, CarbonImmutable $now, string $cycleKey): bool
    {
        $dayPlan = $this->dayPlan($schedule, (int) $now->isoWeekday());
        $dailyLimit = (int) ($dayPlan['daily_limit'] ?? 0);

        if ($dailyLimit <= 0) {
            return true;
        }

        $cycleCount = OutreachSendLog::query()
            ->where('schedule_id', $schedule->id)
            ->where('cycle_key', $cycleKey)
            ->whereIn('status', [
                OutreachSendLog::STATUS_QUEUED,
                OutreachSendLog::STATUS_SENT,
            ])
            ->count();

        return $cycleCount < $dailyLimit;
    }

    private function isDispatchWeek(OutreachSchedule $schedule, CarbonImmutable $now): bool
    {
        $startsOn = CarbonImmutable::parse($schedule->starts_on)->startOfDay();

        if ($now->startOfDay()->lt($startsOn)) {
            return false;
        }

        if (Str::lower((string) $schedule->recurrence) !== 'biweekly') {
            return true;
        }

        $anchor = $startsOn->startOfWeek();
        $currentWeek = $now->startOfWeek();
        $weeksBetween = (int) floor($anchor->diffInDays($currentWeek, false) / 7);

        return $weeksBetween >= 0 && $weeksBetween % 2 === 0;
    }

    private function hasConfiguredButInactiveSenderAccounts(string $audience): bool
    {
        return \App\Models\OutreachSenderAccount::query()
            ->where('audience', $audience)
            ->exists();
    }
}
