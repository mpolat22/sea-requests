<?php

namespace App\Console\Commands;

use App\Models\Rfq;
use App\Notifications\MarketplaceNotification;
use Illuminate\Console\Command;

class AutoCloseOverdueRfqs extends Command
{
    protected $signature = 'rfqs:auto-close-overdue';

    protected $description = 'Close submitted RFQs whose due date has already passed.';

    public function handle(): int
    {
        $rfqs = Rfq::query()
            ->with('buyer')
            ->where('status', Rfq::STATUS_SUBMITTED)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today()->toDateString())
            ->get();

        $count = 0;

        foreach ($rfqs as $rfq) {
            $rfq->forceFill([
                'status' => Rfq::STATUS_CLOSED,
            ])->save();

            if ($rfq->buyer) {
                $rfq->buyer->notify(new MarketplaceNotification(
                    $this->notificationContent($rfq)
                ));
            }

            $count++;
        }

        $this->info("Closed {$count} overdue RFQ(s).");

        return self::SUCCESS;
    }

    private function notificationContent(Rfq $rfq): array
    {
        return [
            'tone' => 'warning',
            'action_url' => $rfq->buyerShowUrl(),
            'en' => [
                'subject' => 'Sea Requests | RFQ Closed After Due Date',
                'title' => 'RFQ Closed After Due Date',
                'message' => 'Your RFQ was closed automatically because the due date has passed.',
                'details' => [
                    ['label' => 'Reference No', 'value' => $rfq->reference_no],
                    ['label' => 'Due Date', 'value' => optional($rfq->due_date)->format('Y-m-d') ?: '-'],
                    ['label' => 'Priority', 'value' => ucfirst((string) $rfq->priority)],
                ],
                'action_label' => 'Review RFQ',
            ],
        ];
    }
}
