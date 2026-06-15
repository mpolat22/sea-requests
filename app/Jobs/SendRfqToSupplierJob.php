<?php

namespace App\Jobs;

use App\Models\RfqSupplierRecipient;
use App\Notifications\MarketplaceNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendRfqToSupplierJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $recipientId
    ) {
        $this->onQueue('rfq-delivery');
    }

    public function handle(): void
    {
        $recipient = RfqSupplierRecipient::query()
            ->with(['rfq.buyer', 'seller'])
            ->find($this->recipientId);

        if (! $recipient) {
            return;
        }

        $seller = $recipient->seller;
        $rfq = $recipient->rfq;

        if (! $seller || ! $rfq) {
            $recipient->forceFill([
                'delivery_status' => 'skipped',
                'failed_at' => now(),
                'delivery_error' => 'Seller or RFQ record is missing.',
                'delivery_attempts' => $recipient->delivery_attempts + 1,
            ])->save();

            return;
        }

        if (! $rfq->canReceiveSupplierResponses()) {
            $recipient->forceFill([
                'delivery_status' => 'skipped',
                'failed_at' => now(),
                'delivery_error' => 'RFQ is closed for supplier responses.',
                'delivery_attempts' => $recipient->delivery_attempts + 1,
            ])->save();

            return;
        }

        $seller->notify(new MarketplaceNotification(
            $this->notificationContent($recipient),
            ['mail', 'database']
        ));

        $recipient->forceFill([
            'delivery_status' => 'sent',
            'delivered_at' => now(),
            'failed_at' => null,
            'delivery_error' => null,
            'delivery_attempts' => $recipient->delivery_attempts + 1,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $recipient = RfqSupplierRecipient::query()->find($this->recipientId);

        if (! $recipient) {
            return;
        }

        $recipient->forceFill([
            'delivery_status' => 'failed',
            'failed_at' => now(),
            'delivery_error' => mb_substr($exception->getMessage(), 0, 2000),
            'delivery_attempts' => $recipient->delivery_attempts + 1,
        ])->save();
    }

    private function notificationContent(RfqSupplierRecipient $recipient): array
    {
        $rfq = $recipient->rfq;
        $actionUrl = route('seller.requests');

        $baseDetails = [
            ['label' => 'Reference No', 'value' => $rfq->reference_no],
            ['label' => 'Country', 'value' => $recipient->country_name ?: $rfq->country_name],
            ['label' => 'Port', 'value' => $recipient->port_name ?: $rfq->port_name],
            ['label' => 'Requisition Date', 'value' => optional($rfq->requisition_date)->format('Y-m-d') ?: '-'],
            ['label' => 'Due Date', 'value' => optional($rfq->due_date)->format('Y-m-d') ?: '-'],
        ];

        if ($rfq->request_type === 'service_request') {
            $details = array_merge($baseDetails, [
                ['label' => 'Service Request', 'value' => $rfq->service_title ?: 'Service Request'],
                ['label' => 'Description', 'value' => $rfq->service_description ?: '-'],
            ]);

            return [
                'tone' => 'info',
                'action_url' => $actionUrl,
                'en' => [
                    'subject' => 'Sea Requests | New Service RFQ Available',
                    'title' => 'New Service RFQ Available',
                    'message' => 'A new service RFQ matching your business scope has been assigned to your company.',
                    'details' => $details,
                    'action_label' => 'Open Supplier Dashboard',
                ],
            ];
        }

        $details = array_merge($baseDetails, [
            ['label' => 'Priority', 'value' => ucfirst((string) $rfq->priority)],
        ]);

        return [
            'tone' => 'info',
            'action_url' => $actionUrl,
            'en' => [
                'subject' => 'Sea Requests | New Spare Parts RFQ Available',
                'title' => 'New Spare Parts RFQ Available',
                'message' => 'A new spare parts RFQ matching your business scope has been assigned to your company.',
                'details' => $details,
                'action_label' => 'Open Supplier Dashboard',
            ],
        ];
    }
}
