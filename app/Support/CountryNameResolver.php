<?php

namespace App\Support;

use Illuminate\Support\Str;

class CountryNameResolver
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $core = [
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AR' => 'Argentina',
            'AE' => 'United Arab Emirates',
            'AT' => 'Austria',
            'AU' => 'Australia',
            'AZ' => 'Azerbaijan',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BE' => 'Belgium',
            'BR' => 'Brazil',
            'BG' => 'Bulgaria',
            'CA' => 'Canada',
            'CL' => 'Chile',
            'CN' => 'China',
            'CO' => 'Colombia',
            'HR' => 'Croatia',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DE' => 'Germany',
            'DK' => 'Denmark',
            'EE' => 'Estonia',
            'EG' => 'Egypt',
            'ES' => 'Spain',
            'FI' => 'Finland',
            'FR' => 'France',
            'GE' => 'Georgia',
            'GB' => 'United Kingdom',
            'GL' => 'Greenland',
            'GR' => 'Greece',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IQ' => 'Iraq',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KW' => 'Kuwait',
            'LB' => 'Lebanon',
            'LV' => 'Latvia',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'LY' => 'Libya',
            'LK' => 'Sri Lanka',
            'MT' => 'Malta',
            'MX' => 'Mexico',
            'MD' => 'Moldova',
            'ME' => 'Montenegro',
            'MA' => 'Morocco',
            'IN' => 'India',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'NG' => 'Nigeria',
            'NL' => 'Netherlands',
            'NZ' => 'New Zealand',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PA' => 'Panama',
            'PH' => 'Philippines',
            'PK' => 'Pakistan',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'QA' => 'Qatar',
            'RO' => 'Romania',
            'RU' => 'Russia',
            'SA' => 'Saudi Arabia',
            'RS' => 'Serbia',
            'SE' => 'Sweden',
            'SG' => 'Singapore',
            'SI' => 'Slovenia',
            'SK' => 'Slovakia',
            'TN' => 'Tunisia',
            'TH' => 'Thailand',
            'TR' => 'Turkey',
            'UA' => 'Ukraine',
            'UY' => 'Uruguay',
            'US' => 'United States',
            'VN' => 'Vietnam',
            'YE' => 'Yemen',
            'ZA' => 'South Africa',
        ];

        $vendorRegionsPath = base_path('vendor/nesbot/carbon/src/Carbon/List/regions.php');
        $vendorRegions = is_file($vendorRegionsPath) ? require $vendorRegionsPath : [];

        return array_replace($vendorRegions, $core);
    }

    /**
     * @return string[]
     */
    public static function demoCountryCodes(): array
    {
        return [
            'AE',
            'DE',
            'DK',
            'ES',
            'GB',
            'GR',
            'IT',
            'NL',
            'SG',
            'TR',
            'US',
        ];
    }

    public static function resolve(?string $value): ?string
    {
        if (! filled($value)) {
            return $value;
        }

        $alias = self::canonicalNameAlias($value);

        if ($alias !== null) {
            return $alias;
        }

        $normalized = strtoupper(trim((string) $value));

        if (strlen($normalized) === 2) {
            if (array_key_exists($normalized, self::all())) {
                return self::all()[$normalized];
            }

            if (class_exists(\Locale::class)) {
                $resolved = \Locale::getDisplayRegion('-'.$normalized, 'en');

                if (filled($resolved) && strtoupper($resolved) !== $normalized) {
                    return $resolved;
                }
            }
        }

        return $value;
    }

    public static function codeForName(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = self::normalizedNameKey($value);

        foreach (self::nameAliases() as $alias => $canonicalName) {
            if ($normalized === $alias) {
                return self::codeForName($canonicalName);
            }
        }

        foreach (self::all() as $code => $name) {
            if (self::normalizedNameKey($name) === $normalized) {
                return $code;
            }
        }

        return null;
    }

    private static function canonicalNameAlias(?string $value): ?string
    {
        $normalized = self::normalizedNameKey($value);

        return self::nameAliases()[$normalized] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function nameAliases(): array
    {
        return [
            'turkiye' => 'Turkey',
            'republic of turkey' => 'Turkey',
        ];
    }

    private static function normalizedNameKey(?string $value): string
    {
        return (string) Str::of(Str::ascii(trim((string) $value)))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim();
    }
}
