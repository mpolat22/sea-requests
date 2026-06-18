<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BuyerOrderInformationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_complete_spare_parts_order_information_and_move_order_to_invoice_pending(): void
    {
        [$buyer, $seller, $offer] = $this->createConfirmedSparePartsOrder();

        Notification::fake();

        $response = $this
            ->actingAs($buyer)
            ->put(route('buyer.orders.information.update', $offer), [
                'billing_company_name' => 'North Fleet Procurement Ltd.',
                'billing_address' => 'Liman Caddesi 18, Istanbul',
                'billing_tax_id' => 'TR-778899',
                'billing_contact_name' => 'Ayse Demir',
                'billing_contact_email' => 'billing@northfleet.test',
                'billing_contact_phone' => '+90 212 555 0101',
                'delivery_target_type' => 'vessel',
                'delivery_country' => 'Turkey',
                'delivery_port' => 'Istanbul',
                'delivery_address' => 'MV North Star / Istanbul Anchorage',
                'delivery_contact_name' => 'Chief Officer',
                'delivery_contact_email' => 'chief.officer@northfleet.test',
                'delivery_contact_phone' => '+90 212 555 0102',
                'delivery_required_date' => now()->addDays(7)->toDateString(),
            ]);

        $response
            ->assertRedirect(route('buyer.orders.show', $offer))
            ->assertSessionHas('success.code', 'order-information-saved');

        $offer->refresh();

        $this->assertSame(Offer::ORDER_STATUS_INVOICE_PENDING, $offer->order_workflow_status);
        $this->assertSame('North Fleet Procurement Ltd.', $offer->billing_company_name);
        $this->assertSame('Istanbul', $offer->delivery_port);

        Notification::assertSentTo(
            $buyer,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($buyer, $offer): bool {
                $payload = $notification->toArray($buyer);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Order Information Saved'
                    && ($payload['action_url'] ?? null) === route('buyer.orders.show', $offer);
            }
        );

        Notification::assertSentTo(
            $seller,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller, $offer): bool {
                $payload = $notification->toArray($seller);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Order Information Ready'
                    && ($payload['action_url'] ?? null) === route('seller.orders.show', $offer);
            }
        );

        $this->actingAs($buyer)
            ->get(route('buyer.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/Dashboard/OrderDetail')
                ->where('order.offer_id', $offer->id)
                ->where('order.order_workflow_status', Offer::ORDER_STATUS_INVOICE_PENDING)
                ->where('order.order_workflow_status_label', 'Invoice Pending')
                ->where('order.billing_company_name', 'North Fleet Procurement Ltd.')
                ->where('order.delivery_port', 'Istanbul')
                ->where('order.selected_items.0.requested_manufacturer', 'Requested Maker')
                ->where('order.selected_items.0.requested_comments', 'Install in the port-side cooling line.')
                ->where('order.selected_items.0.offered_manufacturer', 'Maker A')
                ->where('order.selected_items.0.offered_qty', '5')
                ->where('order.selected_items.0.selected_qty', '2')
                ->where('order.selected_items.0.offer_remarks', 'Quoted as requested.')
                ->where('order.selected_items.0.request_attachments.0.name', 'buyer-spec.pdf')
                ->where('order.selected_items.0.offer_attachments.0.name', 'accepted-offer.pdf')
            );
    }

    public function test_buyer_order_information_update_can_return_to_orders_list(): void
    {
        [$buyer, $seller, $offer] = $this->createConfirmedSparePartsOrder();

        $response = $this
            ->actingAs($buyer)
            ->put(route('buyer.orders.information.update', $offer), [
                'return_to' => 'orders',
                'billing_company_name' => 'North Fleet Procurement Ltd.',
                'billing_address' => 'Liman Caddesi 18, Istanbul',
                'billing_tax_id' => 'TR-778899',
                'billing_contact_name' => 'Ayse Demir',
                'billing_contact_email' => 'billing@northfleet.test',
                'billing_contact_phone' => '+90 212 555 0101',
                'delivery_target_type' => 'vessel',
                'delivery_country' => 'Turkey',
                'delivery_port' => 'Istanbul',
                'delivery_address' => 'MV North Star / Istanbul Anchorage',
                'delivery_contact_name' => 'Chief Officer',
                'delivery_contact_email' => 'chief.officer@northfleet.test',
                'delivery_contact_phone' => '+90 212 555 0102',
                'delivery_required_date' => now()->addDays(7)->toDateString(),
            ]);

        $response
            ->assertRedirect(route('buyer.orders'))
            ->assertSessionHas('success.code', 'order-information-saved');

        $offer->refresh();

        $this->assertSame(Offer::ORDER_STATUS_INVOICE_PENDING, $offer->order_workflow_status);
        $this->assertSame('North Fleet Procurement Ltd.', $offer->billing_company_name);
    }

    public function test_service_order_information_is_visible_on_supplier_order_detail_after_buyer_saves_it(): void
    {
        [$buyer, $seller, $offer] = $this->createConfirmedServiceOrder();

        $this
            ->actingAs($buyer)
            ->put(route('buyer.orders.information.update', $offer), [
                'billing_company_name' => 'Blue Ocean Shipping LLC',
                'billing_address' => 'Ataturk Bulvari 22, Izmir',
                'billing_tax_id' => 'TR-554433',
                'billing_contact_name' => 'Mert Kaya',
                'billing_contact_email' => 'ap@blueocean.test',
                'billing_contact_phone' => '+90 232 555 0101',
                'service_location_type' => 'port',
                'service_location' => 'Aliaga Port / Berth 4',
                'service_contact_name' => 'Superintendent',
                'service_contact_email' => 'superintendent@blueocean.test',
                'service_contact_phone' => '+90 232 555 0102',
                'service_required_date' => now()->addDays(3)->toDateString(),
                'service_instruction_notes' => 'Attendance requires port pass and toolbox talk before boarding.',
            ])
            ->assertRedirect(route('buyer.orders.show', $offer));

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Supplier/Dashboard/OrderDetail')
                ->where('order.order_workflow_status', Offer::ORDER_STATUS_INVOICE_PENDING)
                ->where('order.order_workflow_status_label', 'Invoice Pending')
                ->where('order.billing_company_name', 'Blue Ocean Shipping LLC')
                ->where('order.service_description', 'Hydraulic service scope')
                ->where('order.service_location', 'Aliaga Port / Berth 4')
                ->where('order.service_instruction_notes', 'Attendance requires port pass and toolbox talk before boarding.')
                ->where('order.request_attachments.0.name', 'service-scope.pdf')
                ->where('order.offer_attachments.0.name', 'attendance-plan.pdf')
            );
    }

    public function test_buyer_sees_update_success_code_when_order_information_is_edited_again(): void
    {
        [$buyer, $seller, $offer] = $this->createConfirmedSparePartsOrder();

        Notification::fake();

        $this
            ->actingAs($buyer)
            ->put(route('buyer.orders.information.update', $offer), [
                'billing_company_name' => 'North Fleet Procurement Ltd.',
                'billing_address' => 'Liman Caddesi 18, Istanbul',
                'billing_tax_id' => 'TR-778899',
                'billing_contact_name' => 'Ayse Demir',
                'billing_contact_email' => 'billing@northfleet.test',
                'billing_contact_phone' => '+90 212 555 0101',
                'delivery_target_type' => 'vessel',
                'delivery_country' => 'Turkey',
                'delivery_port' => 'Istanbul',
                'delivery_address' => 'MV North Star / Istanbul Anchorage',
                'delivery_contact_name' => 'Chief Officer',
                'delivery_contact_email' => 'chief.officer@northfleet.test',
                'delivery_contact_phone' => '+90 212 555 0102',
                'delivery_required_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirect(route('buyer.orders.show', $offer))
            ->assertSessionHas('success.code', 'order-information-saved');

        $offer->refresh();

        $this->assertSame(Offer::ORDER_STATUS_INVOICE_PENDING, $offer->order_workflow_status);

        $response = $this
            ->actingAs($buyer)
            ->put(route('buyer.orders.information.update', $offer), [
                'billing_company_name' => 'North Fleet Procurement Ltd.',
                'billing_address' => 'Liman Caddesi 21, Istanbul',
                'billing_tax_id' => 'TR-778899',
                'billing_contact_name' => 'Ayse Demir',
                'billing_contact_email' => 'billing@northfleet.test',
                'billing_contact_phone' => '+90 212 555 0101',
                'delivery_target_type' => 'vessel',
                'delivery_country' => 'Turkey',
                'delivery_port' => 'Gebze',
                'delivery_address' => 'MV North Star / Gebze Anchorage',
                'delivery_contact_name' => 'Chief Officer',
                'delivery_contact_email' => 'chief.officer@northfleet.test',
                'delivery_contact_phone' => '+90 212 555 0102',
                'delivery_required_date' => now()->addDays(10)->toDateString(),
            ]);

        $response
            ->assertRedirect(route('buyer.orders.show', $offer))
            ->assertSessionHas('success.code', 'order-information-updated');

        $offer->refresh();

        $this->assertSame('Gebze', $offer->delivery_port);
        $this->assertSame('Liman Caddesi 21, Istanbul', $offer->billing_address);

        Notification::assertSentTo(
            $buyer,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($buyer, $offer): bool {
                $payload = $notification->toArray($buyer);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Order Information Updated'
                    && ($payload['action_url'] ?? null) === route('buyer.orders.show', $offer);
            }
        );

        Notification::assertSentTo(
            $seller,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller, $offer): bool {
                $payload = $notification->toArray($seller);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Order Information Updated'
                    && ($payload['action_url'] ?? null) === route('seller.orders.show', $offer);
            }
        );
    }

    private function createConfirmedSparePartsOrder(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createReadySeller('Atlas Marine', 'TR', 'Turkey', 'Istanbul', 'TRIST');

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-ORDER-INFO-SPARE-001',
            'company_name' => 'North Fleet',
            'ship_name' => 'MV North Star',
            'request_type' => 'spare_parts',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
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
            'status' => Rfq::STATUS_CLOSED,
            'general_notes' => 'Spare order information flow test.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $rfqItem = RfqItem::query()->create([
            'rfq_id' => $rfq->id,
            'line_no' => 1,
            'product_name' => 'Spray nozzle',
            'part_no' => 'SN-001',
            'quantity' => '2',
            'unit' => 'PCS',
            'manufacturer' => 'Requested Maker',
            'quality' => 'genuine',
            'comments' => 'Install in the port-side cooling line.',
        ]);

        $rfqItem->attachments()->create([
            'disk' => 'public',
            'path' => 'rfqs/order-info/buyer-spec.pdf',
            'original_name' => 'buyer-spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $offer = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'spare_parts',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'delivery_terms' => 'FOB',
            'award_scope_policy' => Offer::AWARD_SCOPE_PARTIAL_ALLOWED,
            'payment_order_confirmation' => 30,
            'payment_before_shipment' => 70,
            'submitted_at' => now(),
            'order_workflow_status' => Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING,
        ]);

        $offerItem = $offer->items()->create([
            'rfq_item_id' => $rfqItem->id,
            'line_no' => 1,
            'offer_qty' => 5,
            'unit_price' => 10,
            'line_total' => 50,
            'delivery_time' => '10',
            'quality' => 'genuine',
            'manufacturer' => 'Maker A',
            'remarks' => 'Quoted as requested.',
        ]);

        $offerItem->attachments()->create([
            'disk' => 'public',
            'path' => 'offers/order-info/accepted-offer.pdf',
            'original_name' => 'accepted-offer.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ]);

        OfferAward::query()->create([
            'rfq_id' => $rfq->id,
            'buyer_id' => $buyer->id,
            'offer_id' => $offer->id,
            'offer_item_id' => $offerItem->id,
            'rfq_item_id' => $rfqItem->id,
            'request_type' => 'spare_parts',
            'status' => OfferAward::STATUS_CONFIRMED,
            'awarded_quantity' => 2,
            'buyer_note' => 'Proceed with this line.',
            'confirmed_at' => now(),
        ]);

        return [$buyer, $seller, $offer];
    }

    private function createConfirmedServiceOrder(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createReadySeller('Atlas Marine', 'TR', 'Turkey', 'Izmir', 'TRIZM');

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-ORDER-INFO-SERVICE-001',
            'company_name' => 'Blue Ocean Shipping',
            'ship_name' => 'MV Blue Wave',
            'request_type' => 'service_request',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => 'Turkey',
            'port_name' => 'Izmir',
            'country_names' => ['Turkey'],
            'ports_by_country' => [
                'Turkey' => [
                    ['id' => 2, 'name' => 'Izmir', 'unlocode' => 'TRIZM'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_CLOSED,
            'general_notes' => 'Service order information flow test.',
            'service_title' => 'Hydraulic Attendance',
            'service_description' => 'Hydraulic service scope',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $offer = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'service_request',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'grand_total' => 1250,
            'delivery_terms' => 'DAP',
            'other_delivery_terms' => 'Attendance at nominated berth',
            'payment_order_confirmation' => 30,
            'payment_before_shipment' => 70,
            'service_clarification' => 'Attendance with hydraulic specialist.',
            'submitted_at' => now(),
            'order_workflow_status' => Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING,
        ]);

        $rfq->attachments()->create([
            'disk' => 'public',
            'path' => 'rfqs/order-info/service-scope.pdf',
            'original_name' => 'service-scope.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1500,
        ]);

        $offer->attachments()->create([
            'disk' => 'public',
            'path' => 'offers/order-info/attendance-plan.pdf',
            'original_name' => 'attendance-plan.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1700,
        ]);

        OfferAward::query()->create([
            'rfq_id' => $rfq->id,
            'buyer_id' => $buyer->id,
            'offer_id' => $offer->id,
            'offer_item_id' => null,
            'rfq_item_id' => null,
            'request_type' => 'service_request',
            'status' => OfferAward::STATUS_CONFIRMED,
            'awarded_quantity' => 1,
            'buyer_note' => 'Proceed with this attendance.',
            'confirmed_at' => now(),
        ]);

        return [$buyer, $seller, $offer];
    }

    private function createReadySeller(string $companyName, string $countryCode, string $countryName, string $portName, string $unlocode): User
    {
        $port = Port::query()->firstOrCreate(
            ['unlocode' => $unlocode],
            [
                'country_code' => $countryCode,
                'location_code' => substr($unlocode, -3),
                'country_name' => $countryName,
                'port_name' => $portName,
                'is_active' => true,
            ]
        );

        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => $companyName,
            'country' => $countryName,
            'email_verified_at' => now(),
            'approval_status' => 'approved',
            'approved_at' => now(),
            'phone' => '+90 5551234567',
            'contact_email' => 'atlas-marine@example.test',
            'company_address_line' => "{$portName} Harbor Center",
            'company_city' => $portName,
            'company_overview' => str_repeat("{$companyName} maintains a fully approved supplier profile with verified marine capability, documented ports, and complete compliance records. ", 4),
            'company_logo_path' => 'offers/1/items/1/SRDOM41GUldPys4w8HppPoUCBlQx1IWaUM5vo8DA.png',
            'service_category_ids' => [1],
            'service_country_codes' => [$countryCode],
            'registration_number' => "{$countryCode}-BUYER-ORDER-001",
            'company_registration_documents' => [['path' => 'rfqs/10/items/21/fJ1lllxW2nFcmHe9CNEwUXsT7fcs5XmaqNJ2aztC.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        return $seller;
    }
}
