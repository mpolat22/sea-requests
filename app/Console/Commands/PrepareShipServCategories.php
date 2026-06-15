<?php

namespace App\Console\Commands;

use App\Support\ShipServCategoryCleanupEngine;
use Illuminate\Console\Command;

class PrepareShipServCategories extends Command
{
    protected $signature = 'shipserv:prepare-categories {--limit= : Optionally process only a subset of imported categories}';

    protected $description = 'Normalize and classify imported ShipServ categories before publishing';

    public function handle(ShipServCategoryCleanupEngine $engine): int
    {
        $limit = $this->option('limit');
        $limit = $limit !== null ? max(1, (int) $limit) : null;

        $summary = $engine->prepare($limit);

        $this->table(
            ['Metric', 'Value'],
            collect($summary)->map(fn ($value, $key) => [$key, (string) $value])->values()->all(),
        );

        return self::SUCCESS;
    }
}
