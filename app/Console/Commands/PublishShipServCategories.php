<?php

namespace App\Console\Commands;

use App\Support\ShipServCategoryCleanupEngine;
use Illuminate\Console\Command;

class PublishShipServCategories extends Command
{
    protected $signature = 'shipserv:publish-categories
        {--min-confidence=60 : Minimum confidence score required for publish}
        {--activate : Publish as active instead of inactive}';

    protected $description = 'Publish prepared ShipServ category suggestions into local categories and subcategories';

    public function handle(ShipServCategoryCleanupEngine $engine): int
    {
        $minConfidence = max(0, min(100, (int) $this->option('min-confidence')));
        $activate = (bool) $this->option('activate');

        $summary = $engine->publish($minConfidence, $activate);

        $this->table(
            ['Metric', 'Value'],
            collect($summary)->map(fn ($value, $key) => [$key, (string) $value])->values()->all(),
        );

        return self::SUCCESS;
    }
}
