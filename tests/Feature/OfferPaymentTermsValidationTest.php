<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Rfq;
use App\Models\RfqSupplierRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferPaymentTermsValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_offer_submit_requires_at_least_one_payment_term(): void
    {
        [$seller, $rfq] = $this->createServiceRfqWithRecipient();

        $response = $this
            ->actingAs($seller)
            ->from(route('seller.offers.create', $rfq))
            ->post(route('seller.offers.store', $rfq), $this->serviceOfferPayload([
                'payment_order_confirmation' => '',
                'payment_before_shipment' => '',
                'payment_invoice_days' => '',
                'other_payment_terms' => '',
            ]));

        $response->assertRedirect(route('seller.offers.create', $rfq));
        $response->assertSessionHasErrors([
            'payment_terms' => 'At least one payment term is required before submission.',
        ]);
    }

    public function test_service_offer_submit_rejects_payment_percentages_above_hundred(): void
    {
        [$seller, $rfq] = $this->createServiceRfqWithRecipient();

        $response = $this
            ->actingAs($seller)
            ->from(route('seller.offers.create', $rfq))
            ->post(route('seller.offers.store', $rfq), $this->serviceOfferPayload([
                'payment_order_confirmation' => '120',
                'payment_before_shipment' => '',
                'payment_invoice_days' => '',
                'other_payment_terms' => '',
            ]));

        $response->assertRedirect(route('seller.offers.create', $rfq));
        $response->assertSessionHasErrors([
            'payment_order_confirmation' => 'Order confirmation percentage cannot exceed 100.',
            'payment_terms' => 'Payment percentages cannot exceed 100 in total.',
        ]);
    }

    public function test_service_offer_submit_requires_remaining_balance_explanation_when_percentages_do_not_total_one_hundred(): void
    {
        [$seller, $rfq] = $this->createServiceRfqWithRecipient();

        $response = $this
            ->actingAs($seller)
            ->from(route('seller.offers.create', $rfq))
            ->post(route('seller.offers.store', $rfq), $this->serviceOfferPayload([
                'payment_order_confirmation' => '30',
                'payment_before_shipment' => '',
                'payment_invoice_days' => '',
                'other_payment_terms' => '',
            ]));

        $response->assertRedirect(route('seller.offers.create', $rfq));
        $response->assertSessionHasErrors([
            'payment_terms' => 'Explain the remaining balance with days from Invoice Date or Other Payment Terms.',
        ]);
    }

    public function test_service_offer_submit_allows_other_payment_terms_only(): void
    {
        [$seller, $rfq] = $this->createServiceRfqWithRecipient();

        $response = $this
            ->actingAs($seller)
            ->post(route('seller.offers.store', $rfq), $this->serviceOfferPayload([
                'payment_order_confirmation' => '',
                'payment_before_shipment' => '',
                'payment_invoice_days' => '',
                'other_payment_terms' => '100% against signed delivery note and client internal approval.',
            ]));

        $response->assertRedirect(route('seller.requests'));
        $response->assertSessionHas('success');

        $offer = Offer::query()
            ->where('rfq_id', $rfq->id)
            ->where('seller_id', $seller->id)
            ->firstOrFail();

        $this->assertNull($offer->payment_order_confirmation);
        $this->assertNull($offer->payment_before_shipment);
        $this->assertNull($offer->payment_invoice_days);
        $this->assertSame('100% against signed delivery note and client internal approval.', $offer->other_payment_terms);
    }

    /**
     * @return array{0: User, 1: Rfq}
     */
    private function createServiceRfqWithRecipient(): array
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
        ]);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-PAYMENT-TERMS-001',
            'company_name' => 'Terms Buyer Co',
            'ship_name' => 'MV Terms',
            'request_type' => 'service_request',
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
            'country_names' => ['Turkey'],
            'ports_by_country' => [
                'Turkey' => [
                    ['id' => 1, 'name' => 'Istanbul', 'unlocode' => 'TRIST'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Payment term validation RFQ.',
            'service_title' => 'Tank cleaning attendance',
            'service_description' => 'Please quote riding team attendance.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        RfqSupplierRecipient::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'company_name' => $seller->company_name ?: $seller->name,
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
        ]);

        return [$seller, $rfq];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceOfferPayload(array $overrides = []): array
    {
        return array_merge([
            'intent' => 'submit',
            'service_total_price' => '1500',
            'completion_time' => 'Within 12 hours after confirmation',
            'offer_validity' => '7 days',
            'including_tax' => true,
            'tax_amount' => '',
            'including_mobilization' => true,
            'mobilization_cost' => '',
            'including_packing' => true,
            'packing_cost' => '',
            'including_freight' => true,
            'freight_cost' => '',
            'delivery_terms' => 'DAP',
            'other_delivery_terms' => 'Attendance at Istanbul anchorage',
            'payment_order_confirmation' => '30',
            'payment_before_shipment' => '70',
            'payment_invoice_days' => '',
            'other_payment_terms' => '',
            'service_clarification' => 'Certified team attendance included.',
            'general_note' => 'Please share boarding pass details.',
        ], $overrides);
    }
}
