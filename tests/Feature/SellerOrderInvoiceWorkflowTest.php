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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SellerOrderInvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_add_invoice_and_both_order_sides_receive_invoice_list(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$buyer, $seller, $offer] = $this->createInvoicePendingSparePartsOrder();

        $response = $this
            ->actingAs($seller)
            ->post(route('seller.orders.invoices.store', $offer), [
                'return_to' => 'orders',
                'invoice_number' => 'INV-2026-001',
                'invoice_date' => now()->toDateString(),
                'invoice_amount' => '145.50',
                'invoice_notes' => 'Invoice covers the confirmed selected lines and agreed packing cost.',
                'invoice_document' => UploadedFile::fake()->create('invoice-2026-001.pdf', 220, 'application/pdf'),
            ]);

        $response
            ->assertRedirect(route('seller.orders'))
            ->assertSessionHas('success.code', 'invoice-added');

        $offer->refresh();
        $invoice = $offer->invoices()->firstOrFail();

        $this->assertSame(Offer::ORDER_STATUS_INVOICE_UPLOADED, $offer->order_workflow_status);
        $this->assertSame('INV-2026-001', $invoice->invoice_number);
        $this->assertSame('145.50', number_format((float) $invoice->invoice_amount, 2, '.', ''));
        $this->assertNotNull($invoice->invoice_document_path);
        Storage::disk('public')->assertExists($invoice->invoice_document_path);

        Notification::assertSentTo(
            $buyer,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($buyer, $offer): bool {
                $payload = $notification->toArray($buyer);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Supplier Invoice Uploaded'
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
                    && ($payload['title'] ?? null) === 'Invoice Uploaded'
                    && ($payload['action_url'] ?? null) === route('seller.orders.show', $offer);
            }
        );

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Supplier/Dashboard/OrderDetail')
                ->where('order.order_workflow_status', Offer::ORDER_STATUS_INVOICE_UPLOADED)
                ->where('order.order_workflow_status_label', 'Invoice Uploaded')
                ->where('order.invoices.0.invoice_number', 'INV-2026-001')
                ->where('order.invoices.0.invoice_amount', '145.5')
                ->where('order.invoices.0.invoice_document.name', 'invoice-2026-001.pdf')
            );

        $this->actingAs($buyer)
            ->get(route('buyer.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/Dashboard/OrderDetail')
                ->where('order.order_workflow_status', Offer::ORDER_STATUS_INVOICE_UPLOADED)
                ->where('order.order_workflow_status_label', 'Invoice Uploaded')
                ->where('order.invoices.0.invoice_number', 'INV-2026-001')
                ->where('order.invoices.0.invoice_amount', '145.5')
                ->where('order.invoices.0.invoice_document.name', 'invoice-2026-001.pdf')
            );
    }

    public function test_seller_can_add_multiple_invoices_and_edit_an_existing_invoice(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$buyer, $seller, $offer] = $this->createInvoicePendingSparePartsOrder();

        $this
            ->actingAs($seller)
            ->post(route('seller.orders.invoices.store', $offer), [
                'invoice_number' => 'INV-2026-001',
                'invoice_date' => now()->toDateString(),
                'invoice_amount' => '80.00',
                'invoice_notes' => 'First invoice.',
                'invoice_document' => UploadedFile::fake()->create('invoice-first.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('seller.orders.show', $offer))
            ->assertSessionHas('success.code', 'invoice-added');

        $firstInvoice = $offer->invoices()->firstOrFail();
        $oldPath = $firstInvoice->invoice_document_path;

        $this
            ->actingAs($seller)
            ->post(route('seller.orders.invoices.store', $offer), [
                'invoice_number' => 'INV-2026-002',
                'invoice_date' => now()->addDay()->toDateString(),
                'invoice_amount' => '60.00',
                'invoice_notes' => 'Second invoice.',
                'invoice_document' => UploadedFile::fake()->create('invoice-second.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('seller.orders.show', $offer))
            ->assertSessionHas('success.code', 'invoice-added');

        $response = $this
            ->actingAs($seller)
            ->post(route('seller.orders.invoices.update', [$offer, $firstInvoice]), [
                'invoice_number' => 'INV-2026-001-REV1',
                'invoice_date' => now()->addDays(2)->toDateString(),
                'invoice_amount' => '85.00',
                'invoice_notes' => 'Updated invoice version after final review.',
            ]);

        $response
            ->assertRedirect(route('seller.orders.show', $offer))
            ->assertSessionHas('success.code', 'invoice-updated');

        $offer->refresh();
        $firstInvoice->refresh();

        $this->assertSame(Offer::ORDER_STATUS_INVOICE_UPLOADED, $offer->order_workflow_status);
        $this->assertSame('INV-2026-001-REV1', $firstInvoice->invoice_number);
        $this->assertSame('85.00', number_format((float) $firstInvoice->invoice_amount, 2, '.', ''));
        $this->assertSame($oldPath, $firstInvoice->invoice_document_path);
        $this->assertSame(2, $offer->invoices()->count());

        Notification::assertSentTo(
            $buyer,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($buyer, $offer): bool {
                $payload = $notification->toArray($buyer);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Supplier Invoice Updated'
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
                    && ($payload['title'] ?? null) === 'Invoice Updated'
                    && ($payload['action_url'] ?? null) === route('seller.orders.show', $offer);
            }
        );
    }

    public function test_seller_cannot_make_total_invoices_exceed_agreed_order_total(): void
    {
        Storage::fake('public');

        [$buyer, $seller, $offer] = $this->createInvoicePendingSparePartsOrder();

        $this
            ->actingAs($seller)
            ->post(route('seller.orders.invoices.store', $offer), [
                'invoice_number' => 'INV-2026-001',
                'invoice_date' => now()->toDateString(),
                'invoice_amount' => '80.00',
                'invoice_notes' => 'First invoice.',
                'invoice_document' => UploadedFile::fake()->create('invoice-first.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('seller.orders.show', $offer))
            ->assertSessionHas('success.code', 'invoice-added');

        $response = $this
            ->actingAs($seller)
            ->from(route('seller.orders.show', $offer))
            ->post(route('seller.orders.invoices.store', $offer), [
                'invoice_number' => 'INV-2026-002',
                'invoice_date' => now()->addDay()->toDateString(),
                'invoice_amount' => '70.00',
                'invoice_notes' => 'Second invoice exceeding the remaining agreed total.',
                'invoice_document' => UploadedFile::fake()->create('invoice-second.pdf', 200, 'application/pdf'),
            ]);

        $response
            ->assertRedirect(route('seller.orders.show', $offer))
            ->assertSessionHasErrors([
                'invoice_amount' => 'Invoice amount cannot exceed the remaining agreed total of USD 65.5.',
            ]);

        $offer->refresh();

        $this->assertSame(1, $offer->invoices()->count());
        $this->assertSame(Offer::ORDER_STATUS_INVOICE_UPLOADED, $offer->order_workflow_status);
    }

    private function createInvoicePendingSparePartsOrder(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createReadySeller('Atlas Marine', 'TR', 'Turkey', 'Istanbul', 'TRIST');

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SELLER-INVOICE-001',
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
            'general_notes' => 'Seller invoice workflow test.',
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
            'including_tax' => false,
            'tax_amount' => 10.50,
            'including_packing' => false,
            'packing_cost' => 15.00,
            'including_freight' => true,
            'submitted_at' => now(),
            'order_workflow_status' => Offer::ORDER_STATUS_INVOICE_PENDING,
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

        $offerItem = $offer->items()->create([
            'rfq_item_id' => $rfqItem->id,
            'line_no' => 1,
            'offer_qty' => 2,
            'unit_price' => 60,
            'line_total' => 120,
            'delivery_time' => '10',
            'quality' => 'genuine',
            'manufacturer' => 'Maker A',
            'remarks' => 'Quoted as requested.',
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
            'registration_number' => "{$countryCode}-SELLER-INVOICE-001",
            'company_registration_documents' => [['path' => 'rfqs/10/items/21/fJ1lllxW2nFcmHe9CNEwUXsT7fcs5XmaqNJ2aztC.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        return $seller;
    }
}
