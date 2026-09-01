<?php

namespace App\Console\Commands;

use App\Support\Outreach\OutreachScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DispatchOutreachEmailsCommand extends Command
{
    protected $signature = 'outreach:dispatch-due';

    protected $description = 'Queue due outreach emails on the isolated outreach worker queue.';

    public function handle(OutreachScheduler $scheduler): int
    {
        if (! config('features.admin_outreach')) {
            $this->components->info('Supplier outreach is disabled.');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now('Europe/Istanbul');
        $queued = 0;

        foreach ($scheduler->dueSchedules($now) as $schedule) {
            if ($scheduler->dispatchSchedule($schedule, $now)) {
                $queued++;
            }
        }

        $this->info("Queued {$queued} outreach email(s).");

        return self::SUCCESS;
    }
}
