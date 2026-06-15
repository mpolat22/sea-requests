<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\OfferInvoice;

class OfferOrderWorkflow
{
    public function resolveStatus(Offer $offer): string
    {
        if ($offer->order_workflow_status === Offer::ORDER_STATUS_COMPLETED) {
            return Offer::ORDER_STATUS_COMPLETED;
        }

        if (! $offer->hasCompleteOrderInformation()) {
            return Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING;
        }

        $invoices = $offer->relationLoaded('invoices')
            ? $offer->invoices
            : $offer->invoices()->get();

        if ($invoices->isEmpty()) {
            return Offer::ORDER_STATUS_INVOICE_PENDING;
        }

        if ($invoices->every(fn (OfferInvoice $invoice) => $invoice->isPaymentConfirmed())) {
            return Offer::ORDER_STATUS_COMPLETED;
        }

        if ($invoices->contains(fn (OfferInvoice $invoice) => $invoice->canSellerConfirmPayment())) {
            return Offer::ORDER_STATUS_PAYMENT_PROOF_UPLOADED;
        }

        return Offer::ORDER_STATUS_INVOICE_UPLOADED;
    }

    public function sync(Offer $offer): string
    {
        $status = $this->resolveStatus($offer);

        if ($offer->order_workflow_status !== $status) {
            $offer->forceFill([
                'order_workflow_status' => $status,
            ])->save();
        }

        return $status;
    }

    public function label(?string $status): string
    {
        return match ($status) {
            Offer::ORDER_STATUS_INVOICE_PENDING => 'Invoice Pending',
            Offer::ORDER_STATUS_INVOICE_UPLOADED => 'Invoice Uploaded',
            Offer::ORDER_STATUS_BUYER_PAYMENT_PENDING => 'Payment Pending',
            Offer::ORDER_STATUS_PAYMENT_PROOF_UPLOADED => 'Payment Proof Uploaded',
            Offer::ORDER_STATUS_PAYMENT_CONFIRMED => 'Payment Received Confirmed',
            Offer::ORDER_STATUS_COMPLETED => 'Completed',
            default => 'Order Information Pending',
        };
    }
}
