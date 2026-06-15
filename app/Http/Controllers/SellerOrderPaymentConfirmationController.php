<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\OfferInvoice;
use App\Support\OfferOrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SellerOrderPaymentConfirmationController extends Controller
{
    public function __construct(
        protected OfferOrderWorkflow $workflow
    ) {}

    public function store(Request $request, Offer $offer, OfferInvoice $invoice): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isSeller() || $user?->isAdmin(), 403);
        abort_unless($this->canConfirmPayment($user, $offer), 404);
        abort_unless((int) $invoice->offer_id === (int) $offer->id, 404);

        if ($invoice->isPaymentConfirmed()) {
            return redirect($this->targetRoute($request, $offer))
                ->with('error', [
                    'code' => 'payment-already-confirmed',
                ]);
        }

        if (! $invoice->canSellerConfirmPayment()) {
            return redirect($this->targetRoute($request, $offer))
                ->with('error', [
                    'code' => 'payment-proof-required',
                ]);
        }

        $invoice->forceFill([
            'payment_confirmed_at' => now(),
        ])->save();

        $offer->load('invoices');
        $this->workflow->sync($offer);

        return redirect($this->targetRoute($request, $offer))
            ->with('success', [
                'code' => 'payment-confirmed',
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

    private function canConfirmPayment(?object $user, Offer $offer): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return $this->confirmedOrderExists($offer);
        }

        return $user->isSeller() && $this->sellerOwnsConfirmedOrder($user->id, $offer);
    }
}
