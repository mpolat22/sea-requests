<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqSupplierRecipient;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BuyerCompareAwardScopePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_submit_spare_parts_offer_with_full_scope_requirement(): void
    {
        Notification::fake();

        [$buyer, $seller, $rfq, $items] = $this->createSparePartsRfqWithRecipient();

        $response = $this
            ->actingAs($seller)
            ->post(route('seller.offers.store', $rfq), [
                'intent' => 'submit',
                'items' => [
                    [
                        'id' => $items[0]->id,
                        'offer_qty' => '2',
                        'unit_price' => '10',
                        'lead_time' => '10',
                        'quality' => 'genuine',
                        'brand_note' => 'Maker A',
                        'remarks' => 'Ready ex stock.',
                    ],
                    [
                        'id' => $items[1]->id,
                        'offer_qty' => '1',
                        'unit_price' => '20',
                        'lead_time' => '12',
                        'quality' => 'oem',
                        'brand_note' => 'Maker B',
                        'remarks' => 'Available with packing.',
                    ],
                ],
                'including_tax' => true,
                'tax_amount' => '',
                'including_packing' => true,
                'packing_cost' => '',
                'including_freight' => true,
                'freight_cost' => '',
                'delivery_terms' => 'FOB',
                'other_delivery_terms' => 'Istanbul warehouse handover',
                'award_scope_policy' => Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED,
                'payment_order_confirmation' => '30',
                'payment_before_shipment' => '70',
                'payment_invoice_days' => '0',
                'other_payment_terms' => 'Balance before dispatch',
            ]);

        $response->assertRedirect(route('seller.requests'));
        $response->assertSessionHas('success', 'offer-submitted');

        $offer = Offer::query()
            ->where('rfq_id', $rfq->id)
            ->where('seller_id', $seller->id)
            ->with('items')
            ->firstOrFail();

        $this->assertSame(Offer::STATUS_SUBMITTED, $offer->status);
        $this->assertSame(Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED, $offer->awardScopePolicy());
        $this->assertCount(2, $offer->items);
    }

    public function test_buyer_compare_page_exposes_offer_award_scope_policy(): void
    {
        [$buyer, $seller, $rfq, $items] = $this->createSparePartsRfqWithRecipient();

        $offer = $this->createSubmittedSparePartsOffer($rfq, $seller, $items, Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED);

        $this->actingAs($buyer)
            ->get(route('buyer.rfqs.compare', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Compare')
                ->where('rfq.offers.0.id', $offer->id)
                ->where('rfq.offers.0.award_scope_policy', Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED)
                ->where('rfq.offers.0.other_payment_terms', 'Balance before dispatch')
            );
    }

    public function test_buyer_cannot_confirm_partial_selection_when_supplier_requires_full_scope(): void
    {
        [$buyer, $seller, $rfq, $items] = $this->createSparePartsRfqWithRecipient();

        $offer = $this->createSubmittedSparePartsOffer($rfq, $seller, $items, Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED);
        $firstOfferItem = $offer->items->sortBy('line_no')->first();
        $sellerName = $seller->company_name ?: $seller->name;

        $response = $this
            ->actingAs($buyer)
            ->from(route('buyer.rfqs.compare', $rfq))
            ->post(route('buyer.rfqs.awards.store', $rfq), [
                'intent' => 'confirm',
                'spare_item_awards' => [
                    [
                        'offer_item_id' => $firstOfferItem->id,
                        'awarded_quantity' => '2',
                        'buyer_note' => 'Selected only the urgent line.',
                    ],
                ],
            ]);

        $response->assertRedirect(route('buyer.rfqs.compare', $rfq));
        $response->assertSessionHasErrors([
            'spare_item_awards' => "{$sellerName} requires full quoted scope acceptance. Select all quoted lines and quoted quantities from this supplier, or remove this supplier from the award.",
        ]);

        $this->assertDatabaseCount('offer_awards', 0);
    }

    public function test_buyer_can_confirm_full_scope_selection_when_supplier_requires_full_scope(): void
    {
        Notification::fake();

        [$buyer, $seller, $rfq, $items] = $this->createSparePartsRfqWithRecipient();

        $offer = $this->createSubmittedSparePartsOffer($rfq, $seller, $items, Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED);
        $offerItems = $offer->items->sortBy('line_no')->values();

        $response = $this
            ->actingAs($buyer)
            ->post(route('buyer.rfqs.awards.store', $rfq), [
                'intent' => 'confirm',
                'spare_item_awards' => [
                    [
                        'offer_item_id' => $offerItems[0]->id,
                        'awarded_quantity' => '2',
                        'buyer_note' => 'Approved together.',
                    ],
                    [
                        'offer_item_id' => $offerItems[1]->id,
                        'awarded_quantity' => '1',
                        'buyer_note' => 'Approved together.',
                    ],
                ],
            ]);

        $response->assertRedirect(route('buyer.requests'));
        $response->assertSessionHas('success', 'award-confirmed');

        $this->assertDatabaseCount('offer_awards', 2);
        $this->assertDatabaseHas('offer_awards', [
            'rfq_id' => $rfq->id,
            'offer_id' => $offer->id,
            'status' => OfferAward::STATUS_CONFIRMED,
        ]);
        $this->assertSame(
            Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING,
            $offer->fresh()->order_workflow_status
        );

        $this->assertSame(Rfq::STATUS_CLOSED, $rfq->fresh()->status);

        Notification::assertSentTo(
            $seller,
            MarketplaceNotification::class,
            fn (MarketplaceNotification $notification) => $notification->toArray($seller)['action_url'] === route('seller.orders.show', $offer)
        );
    }

    /**
     * @return array{0: User, 1: User, 2: Rfq, 3: array<int, RfqItem>}
     */
    private function createSparePartsRfqWithRecipient(): array
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = Port::query()->firstOrCreate(
            ['unlocode' => 'TRIST'],
            [
                'country_code' => 'TR',
                'location_code' => 'IST',
                'country_name' => 'Turkey',
                'port_name' => 'Istanbul',
                'is_active' => true,
            ]
        );

        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
            'approval_status' => 'approved',
            'approved_at' => now(),
            'seller_verification_submitted_at' => now(),
            'company_name' => 'Scope Seller Co',
            'country' => 'Turkey',
            'phone' => '+90 555 000 0000',
            'contact_email' => 'scope-seller@example.test',
            'company_address_line' => 'Istanbul Harbor Center',
            'company_city' => 'Istanbul',
            'company_overview' => str_repeat('Approved scope seller profile. ', 8),
            'company_logo_path' => 'offers/test/logo.png',
            'service_category_ids' => [1],
            'service_country_codes' => ['TR'],
            'registration_number' => 'TR-SCOPE-001',
            'company_registration_documents' => [['path' => 'rfqs/test/company.pdf', 'name' => 'company.pdf']],
        ]);

        $seller->servicePorts()->sync([$port->id]);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-COMPARE-SCOPE-001',
            'company_name' => 'Scope Buyer Co',
            'ship_name' => 'MV Scope',
            'request_type' => 'spare_parts',
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
            'general_notes' => 'Scope validation RFQ.',
            'items_count' => 2,
            'submitted_at' => now(),
        ]);

        $items = [
            RfqItem::query()->create([
                'rfq_id' => $rfq->id,
                'line_no' => 1,
                'product_name' => 'Spray nozzle',
                'part_no' => 'SN-001',
                'quantity' => '2',
                'unit' => 'PCS',
                'quality' => 'genuine',
            ]),
            RfqItem::query()->create([
                'rfq_id' => $rfq->id,
                'line_no' => 2,
                'product_name' => 'Piston',
                'part_no' => 'PS-002',
                'quantity' => '1',
                'unit' => 'PCS',
                'quality' => 'oem',
            ]),
        ];

        RfqSupplierRecipient::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'company_name' => $seller->company_name ?: $seller->name,
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
        ]);

        return [$buyer, $seller, $rfq, $items];
    }

    /**
     * @param  array<int, RfqItem>  $rfqItems
     */
    private function createSubmittedSparePartsOffer(Rfq $rfq, User $seller, array $rfqItems, string $awardScopePolicy): Offer
    {
        $offer = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'spare_parts',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'including_tax' => true,
            'tax_amount' => 0,
            'including_packing' => true,
            'packing_cost' => 0,
            'including_freight' => true,
            'freight_cost' => 0,
            'total_offer_amount' => 40,
            'grand_total' => 40,
            'delivery_terms' => 'FOB',
            'award_scope_policy' => $awardScopePolicy,
            'payment_order_confirmation' => 30,
            'payment_before_shipment' => 70,
            'payment_invoice_days' => 0,
            'other_payment_terms' => 'Balance before dispatch',
            'submitted_at' => now(),
        ]);

        $offer->items()->create([
            'rfq_item_id' => $rfqItems[0]->id,
            'line_no' => 1,
            'offer_qty' => 2,
            'unit_price' => 10,
            'line_total' => 20,
            'delivery_time' => '10',
            'quality' => 'genuine',
            'manufacturer' => 'Maker A',
            'remarks' => 'Quoted as requested.',
        ]);

        $offer->items()->create([
            'rfq_item_id' => $rfqItems[1]->id,
            'line_no' => 2,
            'offer_qty' => 1,
            'unit_price' => 20,
            'line_total' => 20,
            'delivery_time' => '12',
            'quality' => 'oem',
            'manufacturer' => 'Maker B',
            'remarks' => 'Quoted as requested.',
        ]);

        return $offer->fresh('items');
    }
}
