<?php

namespace App\Console\Commands;

use App\Support\ShipServTaxonomyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportShipServTaxonomy extends Command
{
    protected $signature = 'shipserv:import-taxonomy
        {--categories : Import ShipServ category directory into local import tables}
        {--brands : Import ShipServ brand directory into local import tables}
        {--sync-brands : Publish imported brands into the local brands table}
        {--max-pages=80 : Maximum directory pages to crawl per import}
        {--timeout=20 : HTTP timeout in seconds}';

    protected $description = 'Import ShipServ categories and brands into Sea Requests local taxonomy tables';

    public function handle(ShipServTaxonomyImporter $importer): int
    {
        $shouldImportCategories = (bool) $this->option('categories');
        $shouldImportBrands = (bool) $this->option('brands');

        if (! $shouldImportCategories && ! $shouldImportBrands) {
            $shouldImportCategories = true;
            $shouldImportBrands = true;
        }

        $batchId = (string) Str::uuid();
        $maxPages = max(1, (int) $this->option('max-pages'));
        $timeout = max(5, (int) $this->option('timeout'));
        $syncBrands = (bool) $this->option('sync-brands');

        $this->info("Starting ShipServ taxonomy import batch {$batchId}");

        try {
            if ($shouldImportCategories) {
                $this->line('Importing categories...');
                $summary = $importer->importCategories(
                    batchId: $batchId,
                    maxPages: $maxPages,
                    timeout: $timeout,
                );

                $this->table(
                    ['Metric', 'Value'],
                    collect($summary)->map(fn ($value, $key) => [$key, (string) $value])->values()->all(),
                );
            }

            if ($shouldImportBrands) {
                $this->line('Importing brands...');
                $summary = $importer->importBrands(
                    batchId: $batchId,
                    maxPages: $maxPages,
                    timeout: $timeout,
                    syncBrands: $syncBrands,
                );

                $this->table(
                    ['Metric', 'Value'],
                    collect($summary)->map(fn ($value, $key) => [$key, (string) $value])->values()->all(),
                );
            }
        } catch (\Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info('ShipServ taxonomy import completed.');

        return self::SUCCESS;
    }
}
