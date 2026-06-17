<?php

namespace App\Jobs;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchRfqDeliveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $rfqId
    ) {
    }

    public function handle(): void
    {
        $rfq = Rfq::query()
            ->with('supplierRecipients')
            ->find($this->rfqId);

        if (! $rfq || ! $rfq->canReceiveSupplierResponses()) {
            return;
        }

        foreach ($rfq->supplierRecipients as $recipient) {
            $recipient->forceFill([
                'delivery_status' => 'queued',
                'queued_at' => now(),
                'delivery_error' => null,
            ])->save();

            SendRfqToSupplierJob::dispatch($recipient->id);
        }
    }
}
