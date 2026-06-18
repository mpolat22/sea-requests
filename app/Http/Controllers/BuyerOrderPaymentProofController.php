<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\OfferInvoice;
use App\Support\MarketplaceNotificationCenter;
use App\Support\OfferOrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BuyerOrderPaymentProofController extends Controller
{
    public function __construct(
        protected OfferOrderWorkflow $workflow
    ) {}

    public function update(Request $request, Offer $offer, OfferInvoice $invoice): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isBuyer() || $user?->isAdmin(), 403);
        abort_unless($this->canManagePaymentProof($user, $offer), 404);
        abort_unless((int) $invoice->offer_id === (int) $offer->id, 404);
        abort_unless($invoice->canBuyerManagePaymentProof(), 403);

        $validated = $request->validate($this->paymentProofRules($invoice));

        $payload = [
            'payment_proof_date' => $validated['payment_proof_date'] ?? null,
            'payment_reference' => $this->nullableTrimmed($validated['payment_reference'] ?? null),
            'payment_notes' => $this->nullableTrimmed($validated['payment_notes'] ?? null),
        ];

        if ($request->hasFile('payment_proof_document')) {
            if ($invoice->payment_proof_document_path && $invoice->payment_proof_document_disk) {
                Storage::disk($invoice->payment_proof_document_disk)->delete($invoice->payment_proof_document_path);
            }

            $file = $request->file('payment_proof_document');
            $path = $file->store("offers/{$offer->id}/payment-proofs", 'public');

            $payload = array_merge($payload, [
                'payment_proof_document_disk' => 'public',
                'payment_proof_document_path' => $path,
                'payment_proof_document_name' => $file->getClientOriginalName(),
                'payment_proof_document_mime_type' => $file->getClientMimeType(),
                'payment_proof_document_size' => $file->getSize(),
            ]);
        }

        $hadExistingProof = $invoice->hasPaymentProof();

        $invoice->update($payload);

        $offer->load('invoices');
        $this->workflow->sync($offer);
        MarketplaceNotificationCenter::notifyBuyerPaymentProofSaved($offer, $invoice, $hadExistingProof);
        MarketplaceNotificationCenter::notifySellerPaymentProofSaved($offer, $invoice, $hadExistingProof);

        return redirect($this->targetRoute($request, $offer))
            ->with('success', [
                'code' => $hadExistingProof
                    ? 'payment-proof-updated'
                    : 'payment-proof-uploaded',
            ]);
    }

    private function buyerOwnsConfirmedOrder(int $buyerId, Offer $offer): bool
    {
        return OfferAward::query()
            ->where('offer_id', $offer->id)
            ->where('buyer_id', $buyerId)
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

    private function canManagePaymentProof(?object $user, Offer $offer): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return $this->confirmedOrderExists($offer);
        }

        return $user->isBuyer() && $this->buyerOwnsConfirmedOrder($user->id, $offer);
    }

    private function paymentProofRules(OfferInvoice $invoice): array
    {
        return [
            'payment_proof_date' => ['required', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_notes' => ['nullable', 'string', 'max:2000'],
            'payment_proof_document' => array_filter([
                $invoice->payment_proof_document_path ? 'nullable' : 'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:15360',
            ]),
        ];
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
            ? route('buyer.orders')
            : route('buyer.orders.show', $offer);
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
