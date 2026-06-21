<?php

namespace App\Support\Outreach;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class OutreachLabelParser
{
    public function parsePrimaryLabel(?string $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $primary = trim(Str::before($normalized, ':::'));
        $primary = trim(Str::before($primary, ';'));

        return trim(Str::before($primary, ','));
    }

    public function isSupplierLabel(?string $label): bool
    {
        return str_starts_with(Str::upper(trim((string) $label)), 'SUPPLIER ');
    }

    public function detectRegion(?string $label): ?string
    {
        $normalized = Str::upper(trim((string) $label));

        return match (true) {
            Str::contains($normalized, 'ASIA') => 'asia',
            Str::contains($normalized, 'EUROPE') => 'europe',
            Str::contains($normalized, 'AFRICA') => 'africa',
            Str::contains($normalized, 'NORTHAMERICA') => 'north_america',
            Str::contains($normalized, 'SOUTHAMERICA') => 'south_america',
            Str::contains($normalized, 'OCEION'),
            Str::contains($normalized, 'OCEANIA') => 'oceania',
            default => 'global',
        };
    }

    public function recommendation(string $region, int $contactsCount, int $intervalMinutes = 1): array
    {
        $start = match ($region) {
            'asia' => '05:00',
            'europe' => '13:00',
            'africa' => '12:00',
            'north_america' => '18:00',
            'south_america' => '17:00',
            'oceania' => '04:00',
            default => '14:00',
        };

        $weekday = match ($region) {
            'asia', 'oceania' => 1,
            'europe' => 2,
            'africa' => 3,
            'north_america' => 4,
            'south_america' => 5,
            default => 2,
        };

        $durationMinutes = max(120, $contactsCount * max(1, $intervalMinutes));
        $end = CarbonImmutable::createFromFormat('H:i', $start)
            ->addMinutes($durationMinutes)
            ->format('H:i');

        return [
            'weekday' => $weekday,
            'start_time' => $start,
            'end_time' => $end,
        ];
    }
}
