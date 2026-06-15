<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\OfferInvoice;
use Illuminate\Support\Facades\Storage;

class OfferInvoiceData
{
    public function forBuyer(OfferInvoice $invoice, Offer $offer): array
    {
        return [
            ...$this->base($invoice, $offer),
            'can_upload_payment_proof' => $invoice->canBuyerManagePaymentProof(),
            'update_payment_proof_url' => route('buyer.orders.invoices.payment-proof.update', [$offer, $invoice]),
        ];
    }

    public function forSeller(OfferInvoice $invoice, Offer $offer): array
    {
        return [
            ...$this->base($invoice, $offer),
            'can_edit_invoice' => $offer->canSellerManageInvoices() && ! $invoice->isPaymentConfirmed(),
            'can_confirm_payment' => $invoice->canSellerConfirmPayment(),
            'update_url' => route('seller.orders.invoices.update', [$offer, $invoice]),
            'confirm_payment_url' => route('seller.orders.invoices.payment-confirm.store', [$offer, $invoice]),
        ];
    }

    public function forAdmin(OfferInvoice $invoice, Offer $offer): array
    {
        return [
            ...$this->base($invoice, $offer),
            'can_upload_payment_proof' => $invoice->canBuyerManagePaymentProof(),
            'update_payment_proof_url' => route('admin.orders.invoices.payment-proof.update', [$offer, $invoice]),
            'can_edit_invoice' => $offer->canSellerManageInvoices() && ! $invoice->isPaymentConfirmed(),
            'can_confirm_payment' => $invoice->canSellerConfirmPayment(),
            'update_url' => route('admin.orders.invoices.update', [$offer, $invoice]),
            'confirm_payment_url' => route('admin.orders.invoices.payment-confirm.store', [$offer, $invoice]),
        ];
    }

    public function document(
        ?string $disk,
        ?string $path,
        ?string $name,
        ?string $mimeType,
        ?int $size
    ): ?array {
        if (! $path) {
            return null;
        }

        return [
            'name' => $name,
            'url' => Storage::disk($disk ?: 'public')->url($path),
            'mime_type' => $mimeType,
            'size' => $size,
        ];
    }

    private function base(OfferInvoice $invoice, Offer $offer): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?? '',
            'invoice_date' => optional($invoice->invoice_date)?->format('Y-m-d'),
            'invoice_amount' => $invoice->invoice_amount !== null ? $this->decimalString($invoice->invoice_amount) : '',
            'currency' => $invoice->currency ?: $offer->currency ?: $offer->rfq?->currency ?: 'USD',
            'invoice_notes' => $invoice->invoice_notes ?? '',
            'invoice_document' => $this->document(
                $invoice->invoice_document_disk,
                $invoice->invoice_document_path,
                $invoice->invoice_document_name,
                $invoice->invoice_document_mime_type,
                $invoice->invoice_document_size
            ),
            'payment_proof_date' => optional($invoice->payment_proof_date)?->format('Y-m-d'),
            'payment_reference' => $invoice->payment_reference ?? '',
            'payment_notes' => $invoice->payment_notes ?? '',
            'payment_proof_document' => $this->document(
                $invoice->payment_proof_document_disk,
                $invoice->payment_proof_document_path,
                $invoice->payment_proof_document_name,
                $invoice->payment_proof_document_mime_type,
                $invoice->payment_proof_document_size
            ),
            'payment_confirmed_at' => optional($invoice->payment_confirmed_at)?->toISOString(),
            'status' => $invoice->status(),
            'status_label' => $invoice->statusLabel(),
            'created_at' => optional($invoice->created_at)?->toISOString(),
            'updated_at' => optional($invoice->updated_at)?->toISOString(),
        ];
    }

    private function decimalString($value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
