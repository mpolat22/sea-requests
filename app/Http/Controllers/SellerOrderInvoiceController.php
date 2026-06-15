<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\OfferInvoice;
use App\Support\OfferInvoiceTotals;
use App\Support\OfferOrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SellerOrderInvoiceController extends Controller
{
    public function __construct(
        protected OfferOrderWorkflow $workflow,
        protected OfferInvoiceTotals $totals
    ) {}

    public function store(Request $request, Offer $offer): RedirectResponse
    {
        return $this->upsert($request, $offer);
    }

    public function update(Request $request, Offer $offer, OfferInvoice $invoice): RedirectResponse
    {
        abort_unless((int) $invoice->offer_id === (int) $offer->id, 404);

        return $this->upsert($request, $offer, $invoice);
    }

    private function upsert(Request $request, Offer $offer, ?OfferInvoice $invoice = null): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isSeller() || $user?->isAdmin(), 403);
        abort_unless($this->canManageInvoices($user, $offer), 404);
        abort_unless($offer->canSellerManageInvoices(), 403);

        $offer->loadMissing(['rfq', 'invoices', 'awards', 'items']);

        $validated = $request->validate($this->invoiceRules($invoice));
        $this->ensureInvoiceAmountDoesNotExceedAgreement(
            offer: $offer,
            invoice: $invoice,
            rawInvoiceAmount: $validated['invoice_amount'] ?? null
        );

        $payload = [
            'currency' => $offer->currency ?: $offer->rfq?->currency ?: 'USD',
            'invoice_number' => $this->nullableTrimmed($validated['invoice_number'] ?? null),
            'invoice_date' => $validated['invoice_date'] ?? null,
            'invoice_amount' => $validated['invoice_amount'] ?? null,
            'invoice_notes' => $this->nullableTrimmed($validated['invoice_notes'] ?? null),
        ];

        if ($request->hasFile('invoice_document')) {
            if ($invoice?->invoice_document_path && $invoice->invoice_document_disk) {
                Storage::disk($invoice->invoice_document_disk)->delete($invoice->invoice_document_path);
            }

            $file = $request->file('invoice_document');
            $path = $file->store("offers/{$offer->id}/invoices", 'public');

            $payload = array_merge($payload, [
                'invoice_document_disk' => 'public',
                'invoice_document_path' => $path,
                'invoice_document_name' => $file->getClientOriginalName(),
                'invoice_document_mime_type' => $file->getClientMimeType(),
                'invoice_document_size' => $file->getSize(),
            ]);
        }

        if ($invoice) {
            $invoice->update($payload);
            $successCode = 'invoice-updated';
        } else {
            $invoice = $offer->invoices()->create($payload);
            $successCode = 'invoice-added';
        }

        $offer->load('invoices');
        $this->workflow->sync($offer);

        return redirect($this->targetRoute($request, $offer))
            ->with('success', [
                'code' => $successCode,
            ]);
    }

    private function sellerOwnsConfirmedOrder(int $sellerId, Offer $offer): bool
    {
        return (int) $offer->seller_id === $sellerId
            && OfferAward::query()
                ->where('offer_id', $offer->id)
                ->where('status', OfferAward::STATUS_CONFIRMED)
                ->exists();
    }

    private function confirmedOrderExists(Offer $offer): bool
    {
        return OfferAward::query()
            ->where('offer_id', $offer->id)
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->exists();
    }

    private function canManageInvoices(?object $user, Offer $offer): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return $this->confirmedOrderExists($offer);
        }

        return $user->isSeller() && $this->sellerOwnsConfirmedOrder($user->id, $offer);
    }

    private function invoiceRules(?OfferInvoice $invoice): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:120'],
            'invoice_date' => ['required', 'date'],
            'invoice_amount' => ['required', 'numeric', 'gt:0'],
            'invoice_notes' => ['nullable', 'string', 'max:2000'],
            'invoice_document' => array_filter([
                $invoice?->invoice_document_path ? 'nullable' : 'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:15360',
            ]),
        ];
    }

    private function ensureInvoiceAmountDoesNotExceedAgreement(
        Offer $offer,
        ?OfferInvoice $invoice,
        mixed $rawInvoiceAmount
    ): void {
        $invoiceAmount = round((float) $rawInvoiceAmount, 2);
        $allowedMaximum = $this->totals->remainingTotal($offer, $invoice);

        if ($invoiceAmount <= $allowedMaximum + 0.00001) {
            return;
        }

        $currency = $offer->currency ?: $offer->rfq?->currency ?: 'USD';

        throw ValidationException::withMessages([
            'invoice_amount' => sprintf(
                'Invoice amount cannot exceed the remaining agreed total of %s %s.',
                $currency,
                $this->decimalString($allowedMaximum)
            ),
        ]);
    }

    private function targetRoute(Request $request, Offer $offer): string
    {
        $returnTo = (string) $request->input('return_to', 'detail');

        if ($request->user()?->isAdmin()) {
            return $returnTo === 'orders'
                ? route('admin.orders')
                : route('admin.orders.show', $offer);
        }

        return $returnTo === 'orders'
            ? route('seller.orders')
            : route('seller.orders.show', $offer);
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function decimalString(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
