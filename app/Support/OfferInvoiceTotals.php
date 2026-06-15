<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\OfferInvoice;

class OfferInvoiceTotals
{
    public function agreedTotal(Offer $offer): float
    {
        if ($offer->request_type === 'service_request') {
            $baseAmount = (float) ($offer->total_offer_amount ?? 0);
            $taxAmount = $offer->including_tax ? 0.0 : (float) ($offer->tax_amount ?? 0);
            $mobilizationAmount = $offer->including_mobilization ? 0.0 : (float) ($offer->mobilization_cost ?? 0);

            return $this->roundCurrency($baseAmount + $taxAmount + $mobilizationAmount);
        }

        $selectedTotal = $this->selectedTotal($offer);
        $taxAmount = $offer->including_tax ? 0.0 : (float) ($offer->tax_amount ?? 0);
        $packingAmount = $offer->including_packing ? 0.0 : (float) ($offer->packing_cost ?? 0);
        $freightAmount = $offer->including_freight ? 0.0 : (float) ($offer->freight_cost ?? 0);

        return $this->roundCurrency($selectedTotal + $taxAmount + $packingAmount + $freightAmount);
    }

    public function invoicedTotal(Offer $offer, ?OfferInvoice $exceptInvoice = null): float
    {
        $invoices = $offer->relationLoaded('invoices')
            ? $offer->invoices
            : $offer->invoices()->get();

        return $this->roundCurrency(
            $invoices
                ->reject(fn (OfferInvoice $invoice) => $exceptInvoice && (int) $invoice->id === (int) $exceptInvoice->id)
                ->sum(fn (OfferInvoice $invoice) => (float) ($invoice->invoice_amount ?? 0))
        );
    }

    public function remainingTotal(Offer $offer, ?OfferInvoice $exceptInvoice = null): float
    {
        return max(0.0, $this->roundCurrency(
            $this->agreedTotal($offer) - $this->invoicedTotal($offer, $exceptInvoice)
        ));
    }

    public function canAddInvoice(Offer $offer): bool
    {
        return $this->remainingTotal($offer) > 0.00001;
    }

    private function selectedTotal(Offer $offer): float
    {
        $awards = $offer->relationLoaded('awards')
            ? $offer->awards
            : $offer->awards()->where('status', 'confirmed')->get();

        if ($offer->request_type === 'service_request') {
            return (float) ($offer->grand_total ?? 0);
        }

        $offerItems = $offer->relationLoaded('items')
            ? $offer->items->keyBy('id')
            : $offer->items()->get()->keyBy('id');

        return $this->roundCurrency($awards->sum(function ($award) use ($offerItems) {
            $offerItem = $offerItems->get($award->offer_item_id);
            $selectedQty = (float) ($award->awarded_quantity ?? 0);
            $unitPrice = (float) ($offerItem?->unit_price ?? 0);

            return $selectedQty * $unitPrice;
        }));
    }

    private function roundCurrency(float $value): float
    {
        return round($value, 2);
    }
}
