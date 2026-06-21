<?php

namespace App\Support\Outreach;

use App\Models\OutreachContact;
use App\Models\OutreachSchedule;
use App\Models\OutreachSegment;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class OutreachRegionManager
{
    public function __construct(
        protected OutreachLabelParser $labels
    ) {}

    public function regionOptions(): array
    {
        return [
            ['key' => 'asia', 'label' => 'Asia'],
            ['key' => 'europe', 'label' => 'Europe'],
            ['key' => 'africa', 'label' => 'Africa'],
            ['key' => 'north_america', 'label' => 'North America'],
            ['key' => 'south_america', 'label' => 'South America'],
            ['key' => 'oceania', 'label' => 'Oceania'],
            ['key' => 'global', 'label' => 'Global'],
        ];
    }

    public function regionKeys(): array
    {
        return collect($this->regionOptions())
            ->pluck('key')
            ->all();
    }

    public function regionLabel(string $regionKey): string
    {
        $regionKey = $this->normalizeRegionKey($regionKey);

        return collect($this->regionOptions())
            ->firstWhere('key', $regionKey)['label'] ?? 'Global';
    }

    public function canonicalSegmentName(string $regionKey): string
    {
        $regionKey = $this->normalizeRegionKey($regionKey);

        return match ($regionKey) {
            'asia' => 'SUPPLIER ASIA',
            'europe' => 'SUPPLIER EUROPE',
            'africa' => 'SUPPLIER AFRICA',
            'north_america' => 'SUPPLIER NORTHAMERICA',
            'south_america' => 'SUPPLIER SOUTHAMERICA',
            'oceania' => 'SUPPLIER OCEANIA',
            default => 'SUPPLIER GLOBAL',
        };
    }

    public function canonicalSegmentNames(): array
    {
        return collect($this->regionKeys())
            ->mapWithKeys(fn (string $regionKey) => [$regionKey => $this->canonicalSegmentName($regionKey)])
            ->all();
    }

    public function isCanonicalSegmentName(?string $name): bool
    {
        return in_array((string) $name, array_values($this->canonicalSegmentNames()), true);
    }

    public function weekdayOptions(): array
    {
        return [
            ['value' => 1, 'label' => 'Monday'],
            ['value' => 2, 'label' => 'Tuesday'],
            ['value' => 3, 'label' => 'Wednesday'],
            ['value' => 4, 'label' => 'Thursday'],
            ['value' => 5, 'label' => 'Friday'],
            ['value' => 6, 'label' => 'Saturday'],
            ['value' => 7, 'label' => 'Sunday'],
        ];
    }

    public function defaultWeeklyPlan(string $regionKey, int $contactsCount = 0, int $intervalMinutes = 1): array
    {
        $regionKey = $this->normalizeRegionKey($regionKey);
        $recommendation = $this->labels->recommendation($regionKey, $contactsCount, $intervalMinutes);
        $recommendedWeekday = (int) ($recommendation['weekday'] ?? 1);
        $dailyLimit = max(1, $contactsCount > 0 ? $contactsCount : 100);

        return collect($this->weekdayOptions())
            ->map(function (array $weekday) use ($recommendedWeekday, $recommendation, $dailyLimit) {
                $enabled = (int) $weekday['value'] === $recommendedWeekday;

                return [
                    'weekday' => (int) $weekday['value'],
                    'label' => $weekday['label'],
                    'enabled' => $enabled,
                    'start_time' => $enabled ? ($recommendation['start_time'] ?? '09:00') : null,
                    'end_time' => $enabled ? ($recommendation['end_time'] ?? '11:00') : null,
                    'daily_limit' => $enabled ? $dailyLimit : null,
                ];
            })
            ->all();
    }

    public function normalizeWeeklyPlan(string $regionKey, mixed $weeklyPlan, int $contactsCount = 0, int $intervalMinutes = 1): array
    {
        $regionKey = $this->normalizeRegionKey($regionKey);
        $defaults = collect($this->defaultWeeklyPlan($regionKey, $contactsCount, $intervalMinutes))
            ->keyBy('weekday');

        $incoming = collect(is_array($weeklyPlan) ? $weeklyPlan : [])
            ->map(function ($day) {
                if (! is_array($day)) {
                    return null;
                }

                $weekday = (int) ($day['weekday'] ?? 0);

                if ($weekday < 1 || $weekday > 7) {
                    return null;
                }

                return [
                    'weekday' => $weekday,
                    'label' => $this->weekdayLabel($weekday),
                    'enabled' => (bool) ($day['enabled'] ?? false),
                    'start_time' => $day['start_time'] ?: null,
                    'end_time' => $day['end_time'] ?: null,
                    'daily_limit' => filled($day['daily_limit'] ?? null) ? max(1, (int) $day['daily_limit']) : null,
                ];
            })
            ->filter()
            ->keyBy('weekday');

        return $defaults
            ->map(function (array $defaultDay, int $weekday) use ($incoming) {
                $day = $incoming->get($weekday);

                if (! $day) {
                    return $defaultDay;
                }

                return [
                    'weekday' => $weekday,
                    'label' => $defaultDay['label'],
                    'enabled' => (bool) ($day['enabled'] ?? false),
                    'start_time' => $day['start_time'] ?: null,
                    'end_time' => $day['end_time'] ?: null,
                    'daily_limit' => filled($day['daily_limit'] ?? null) ? max(1, (int) $day['daily_limit']) : null,
                ];
            })
            ->values()
            ->all();
    }

    public function weekdayLabel(int $weekday): string
    {
        return collect($this->weekdayOptions())
            ->firstWhere('value', $weekday)['label'] ?? 'Day';
    }

    public function determineRegionKey(
        ?OutreachSegment $primarySegment = null,
        iterable $segments = [],
        ?array $sourcePayload = null
    ): string {
        $candidateLabels = [];

        if ($primarySegment) {
            if (filled($primarySegment->region_key)) {
                return (string) $primarySegment->region_key;
            }

            $candidateLabels[] = $primarySegment->name;
        }

        foreach ($segments as $segment) {
            if ($segment instanceof OutreachSegment) {
                if (filled($segment->region_key)) {
                    return (string) $segment->region_key;
                }

                $candidateLabels[] = $segment->name;
            }
        }

        $payloadRegion = (string) data_get($sourcePayload, 'region_key');

        if (filled($payloadRegion)) {
            return $this->normalizeRegionKey($payloadRegion);
        }

        $candidateLabels[] = data_get($sourcePayload, 'raw_label');
        $candidateLabels[] = data_get($sourcePayload, 'label');

        foreach ($candidateLabels as $label) {
            $region = $this->labels->detectRegion($label);

            if (filled($region)) {
                return $region;
            }
        }

        return 'global';
    }

    public function normalizeRegionKey(?string $regionKey): string
    {
        $normalized = trim((string) $regionKey);

        return in_array($normalized, $this->regionKeys(), true)
            ? $normalized
            : 'global';
    }

    public function ensureCanonicalSegment(string $regionKey): OutreachSegment
    {
        $regionKey = $this->normalizeRegionKey($regionKey);
        $segment = OutreachSegment::query()->firstOrNew([
            'name' => $this->canonicalSegmentName($regionKey),
        ]);

        $segment->audience = 'supplier';
        $segment->region_key = $regionKey;
        $segment->recommended_weekday = $this->labels->recommendation($regionKey, 0)['weekday'];
        $segment->recommended_start_time = $this->labels->recommendation($regionKey, 0)['start_time'];
        $segment->recommended_end_time = $this->labels->recommendation($regionKey, 0)['end_time'];
        $segment->is_active = true;
        $segment->meta = [
            ...Arr::wrap($segment->meta),
            'segment_type' => 'region',
        ];
        $segment->save();

        return $segment;
    }

    public function ensureSourceSegment(string $label): OutreachSegment
    {
        $regionKey = $this->normalizeRegionKey($this->labels->detectRegion($label) ?: 'global');
        $recommendation = $this->labels->recommendation($regionKey, 0);

        $segment = OutreachSegment::query()->firstOrNew(['name' => $label]);
        $segment->audience = 'supplier';
        $segment->region_key = $regionKey;
        $segment->recommended_weekday = $recommendation['weekday'];
        $segment->recommended_start_time = $recommendation['start_time'];
        $segment->recommended_end_time = $recommendation['end_time'];
        $segment->is_active = true;
        $segment->meta = [
            ...Arr::wrap($segment->meta),
            'segment_type' => 'source',
        ];
        $segment->save();

        return $segment;
    }

    public function syncCanonicalSchedule(OutreachSegment $segment, int $contactsCount = 0): OutreachSchedule
    {
        $schedule = OutreachSchedule::query()->firstOrNew(['segment_id' => $segment->id]);
        $interval = max(1, (int) ($schedule->send_interval_minutes ?? 1));
        $existingMeta = is_array($schedule->meta) ? $schedule->meta : [];
        $weeklyPlan = $this->normalizeWeeklyPlan(
            (string) ($segment->region_key ?: 'global'),
            $existingMeta['weekly_plan'] ?? [],
            $contactsCount,
            $interval,
        );

        $firstEnabledDay = collect($weeklyPlan)->first(fn (array $day) => (bool) ($day['enabled'] ?? false));
        $recommendation = $this->labels->recommendation((string) ($segment->region_key ?: 'global'), $contactsCount, $interval);

        $schedule->audience = 'supplier';
        $schedule->recurrence = 'weekly';
        $schedule->starts_on ??= now()->toDateString();
        $schedule->weekday = (int) ($firstEnabledDay['weekday'] ?? $recommendation['weekday']);
        $schedule->suggested_start_time = $recommendation['start_time'];
        $schedule->suggested_end_time = $recommendation['end_time'];
        $schedule->uses_recommended_window = false;
        $schedule->start_time = $firstEnabledDay['start_time'] ?? $recommendation['start_time'];
        $schedule->end_time = $firstEnabledDay['end_time'] ?? $recommendation['end_time'];
        $schedule->send_interval_minutes = $interval;
        $schedule->is_active = $schedule->exists ? (bool) $schedule->is_active : true;
        $schedule->template_rotation ??= [];
        $schedule->meta = [
            ...$existingMeta,
            'weekly_plan' => $weeklyPlan,
        ];
        $schedule->save();

        return $schedule;
    }

    public function normalizeSupplierWorkspace(): void
    {
        $supplierSegments = OutreachSegment::query()
            ->where('audience', 'supplier')
            ->get();

        if ($supplierSegments->isEmpty()) {
            return;
        }

        $canonicalByRegion = [];
        $regionsInUse = [];

        foreach ($supplierSegments as $segment) {
            $regionKey = (string) ($segment->region_key ?: $this->labels->detectRegion($segment->name) ?: 'global');
            $regionsInUse[$regionKey] = $regionKey;

            if ($this->isCanonicalSegmentName($segment->name)) {
                $segment->forceFill([
                    'region_key' => $regionKey,
                    'meta' => [
                        ...Arr::wrap($segment->meta),
                        'segment_type' => 'region',
                    ],
                ])->save();

                $canonicalByRegion[$regionKey] = $segment->fresh();

                continue;
            }

            $segment->forceFill([
                'region_key' => $regionKey,
                'meta' => [
                    ...Arr::wrap($segment->meta),
                    'segment_type' => 'source',
                ],
            ])->save();
        }

        foreach (array_values($regionsInUse) as $regionKey) {
            $canonicalByRegion[$regionKey] ??= $this->ensureCanonicalSegment($regionKey);
        }

        OutreachContact::query()
            ->where('audience', 'supplier')
            ->with([
                'primarySegment:id,name,region_key',
                'segments:id,name,region_key',
            ])
            ->chunkById(200, function (Collection $contacts) use (&$canonicalByRegion) {
                foreach ($contacts as $contact) {
                    $regionKey = $this->normalizeRegionKey($this->determineRegionKey(
                        $contact->primarySegment,
                        $contact->segments,
                        is_array($contact->source_payload) ? $contact->source_payload : null,
                    ));

                    $canonicalSegment = $canonicalByRegion[$regionKey] ??= $this->ensureCanonicalSegment($regionKey);
                    $segmentIds = $contact->segments->pluck('id')->push($canonicalSegment->id)->unique()->values()->all();

                    $sourcePayload = is_array($contact->source_payload) ? $contact->source_payload : [];
                    $sourcePayload['region_key'] = $regionKey;

                    if ($contact->primary_segment_id !== $canonicalSegment->id || $contact->source_payload !== $sourcePayload) {
                        $contact->forceFill([
                            'primary_segment_id' => $canonicalSegment->id,
                            'source_payload' => $sourcePayload,
                        ])->save();
                    }

                    $contact->segments()->syncWithoutDetaching($segmentIds);

                    if ($contact->status !== OutreachContact::STATUS_UNSUBSCRIBED) {
                        $isRegistered = User::query()
                            ->where('role', 'seller')
                            ->whereRaw('lower(email) = ?', [mb_strtolower($contact->email)])
                            ->exists();

                        $expectedStatus = $isRegistered
                            ? OutreachContact::STATUS_REGISTERED
                            : ($contact->status === OutreachContact::STATUS_REPLIED ? OutreachContact::STATUS_REPLIED : OutreachContact::STATUS_ACTIVE);

                        if ($contact->status !== $expectedStatus) {
                            $contact->forceFill([
                                'status' => $expectedStatus,
                            ])->save();
                        }
                    }
                }
            });

        OutreachSchedule::query()
            ->where('audience', 'supplier')
            ->with('segment:id,name')
            ->get()
            ->each(function (OutreachSchedule $schedule) {
                if (! $schedule->segment || ! $this->isCanonicalSegmentName($schedule->segment->name)) {
                    $schedule->forceFill([
                        'is_active' => false,
                    ])->save();
                }
            });

        OutreachSegment::query()
            ->where('audience', 'supplier')
            ->whereIn('name', array_values($this->canonicalSegmentNames()))
            ->withCount([
                'primaryContacts as contacts_count' => fn ($query) => $query->where('audience', 'supplier'),
            ])
            ->get()
            ->each(fn (OutreachSegment $segment) => $this->syncCanonicalSchedule($segment, (int) ($segment->contacts_count ?? 0)));
    }
}
