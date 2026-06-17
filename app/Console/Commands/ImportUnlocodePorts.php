<?php

namespace App\Console\Commands;

use App\Models\Port;
use App\Support\CountryNameResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class ImportUnlocodePorts extends Command
{
    protected $signature = 'ports:import-unlocode {path=storage/app/imports/unlocode_ports.csv : Relative or absolute path to the UN/LOCODE CSV file}';

    protected $description = 'Import UN/LOCODE port data from a CSV file into the ports table.';

    public function handle(): int
    {
        $pathArgument = (string) $this->argument('path');
        $resolvedPath = $this->resolvePath($pathArgument);

        if (! File::exists($resolvedPath)) {
            $this->error("CSV file not found: {$resolvedPath}");

            return self::FAILURE;
        }

        $file = new \SplFileObject($resolvedPath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(';');

        $header = null;
        $rows = [];
        $imported = 0;

        foreach ($file as $record) {
            if (! is_array($record)) {
                continue;
            }

            $record = array_map(
                static fn ($value) => is_string($value) ? trim($value) : $value,
                $record
            );

            if ($this->isEmptyRow($record)) {
                continue;
            }

            if ($header === null) {
                $header = $record;
                continue;
            }

            $mapped = $this->mapRecord($header, $record);

            if (! $mapped) {
                continue;
            }

            $rows[] = $mapped;

            if (count($rows) >= 500) {
                Port::query()->upsert(
                    $rows,
                    ['unlocode'],
                    ['country_code', 'location_code', 'country_name', 'port_name', 'is_active', 'updated_at']
                );

                $imported += count($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            Port::query()->upsert(
                $rows,
                ['unlocode'],
                ['country_code', 'location_code', 'country_name', 'port_name', 'is_active', 'updated_at']
            );

            $imported += count($rows);
        }

        $this->info("UN/LOCODE import complete. {$imported} rows processed.");

        return self::SUCCESS;
    }

    private function resolvePath(string $pathArgument): string
    {
        if (File::exists($pathArgument)) {
            return $pathArgument;
        }

        return base_path($pathArgument);
    }

    private function isEmptyRow(array $record): bool
    {
        return collect($record)->filter(fn ($value) => filled($value))->isEmpty();
    }

    private function mapRecord(array $header, array $record): ?array
    {
        $headerMap = array_map(
            static fn ($value) => strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $value)),
            $header
        );

        $values = [];
        foreach ($headerMap as $index => $key) {
            $values[$key] = Arr::get($record, $index);
        }

        $unlocode = strtoupper((string) ($values['unlocode'] ?? $values['unlocodecode'] ?? ''));
        $portName = trim((string) ($values['portname'] ?? ''));

        if ($unlocode === '' || $portName === '') {
            return null;
        }

        $countryCode = substr($unlocode, 0, 2);
        $locationCode = strlen($unlocode) > 2 ? substr($unlocode, 2, 3) : null;
        $countryName = $this->resolveCountryName($countryCode);
        $timestamp = now();

        return [
            'unlocode' => $unlocode,
            'country_code' => $countryCode,
            'location_code' => $locationCode,
            'country_name' => $countryName,
            'port_name' => $portName,
            'is_active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function resolveCountryName(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));

        if ($countryCode === '') {
            return '';
        }

        $canonical = CountryNameResolver::resolve($countryCode);

        if (is_string($canonical) && $canonical !== '' && strtoupper($canonical) !== $countryCode) {
            return $canonical;
        }

        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.$countryCode, 'en');

            if (is_string($name) && $name !== '' && $name !== $countryCode) {
                return $name;
            }
        }

        return $countryCode;
    }
}
