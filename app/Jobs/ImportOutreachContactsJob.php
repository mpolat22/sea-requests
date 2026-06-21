<?php

namespace App\Jobs;

use App\Models\OutreachImportRun;
use App\Support\Outreach\OutreachCsvImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportOutreachContactsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $importRunId
    ) {
        $this->onQueue('outreach');
    }

    public function handle(OutreachCsvImporter $importer): void
    {
        $run = OutreachImportRun::query()->find($this->importRunId);

        if (! $run) {
            return;
        }

        $run->forceFill([
            'status' => 'processing',
            'message' => 'Import is being processed in the outreach queue.',
        ])->save();

        try {
            $importer->import($run);
        } catch (\Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $run = OutreachImportRun::query()->find($this->importRunId);

        if (! $run) {
            return;
        }

        $run->forceFill([
            'status' => 'failed',
            'message' => $exception?->getMessage() ?: 'Outreach import failed on the queue worker.',
            'completed_at' => now(),
        ])->save();
    }
}
