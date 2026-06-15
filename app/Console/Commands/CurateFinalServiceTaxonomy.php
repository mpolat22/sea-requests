<?php

namespace App\Console\Commands;

use App\Support\FinalServiceTaxonomyCurator;
use Illuminate\Console\Command;

class CurateFinalServiceTaxonomy extends Command
{
    protected $signature = 'taxonomy:curate-service {--activate : Activate the curated categories and subcategories for live use}';

    protected $description = 'Merge staged and legacy service taxonomy into the final curated marine category structure';

    public function handle(FinalServiceTaxonomyCurator $curator): int
    {
        $summary = $curator->curate((bool) $this->option('activate'));

        $this->table(
            ['Metric', 'Value'],
            collect($summary)->map(fn ($value, $key) => [$key, (string) $value])->values()->all(),
        );

        return self::SUCCESS;
    }
}
