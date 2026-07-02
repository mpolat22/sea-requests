<?php

namespace App\Support;

use App\Models\Port;

class AuthCountryCatalog
{
    /**
     * @return array<int, array{code: string, name: string}>
     */
    public static function serviceCountries(): array
    {
        return Port::query()
            ->active()
            ->select('country_code', 'country_name')
            ->distinct()
            ->get()
            ->map(function (Port $port): array {
                $code = strtoupper(trim((string) $port->country_code));
                $fallbackName = trim((string) $port->country_name);
                $name = CountryNameResolver::resolve($code !== '' ? $code : $fallbackName) ?? $fallbackName;

                return [
                    'code' => $code,
                    'name' => $name,
                ];
            })
            ->filter(fn (array $country): bool => filled($country['name']))
            ->unique(fn (array $country): string => $country['code'] !== '' ? $country['code'] : strtolower($country['name']))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return string[]
     */
    public static function countryNames(): array
    {
        return collect(self::serviceCountries())
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public static function countryOptions(): array
    {
        return collect(self::countryNames())
            ->map(fn (string $country): array => [
                'label' => $country,
                'value' => $country,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public static function dialCodeOptions(): array
    {
        $dialingCodes = CountryDialingCodeCatalog::byIsoCode();

        return collect(self::serviceCountries())
            ->map(function (array $country) use ($dialingCodes): ?array {
                $code = strtoupper(trim((string) ($country['code'] ?? '')));
                $name = trim((string) ($country['name'] ?? ''));
                $dialCode = $dialingCodes[$code] ?? null;

                if ($name === '' || ! is_string($dialCode) || $dialCode === '') {
                    return null;
                }

                return [
                    'label' => sprintf('%s (%s)', $name, $dialCode),
                    'value' => $dialCode,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}