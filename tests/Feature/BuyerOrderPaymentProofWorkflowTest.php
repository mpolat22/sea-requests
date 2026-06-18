<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\OfferInvoice;
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

class BuyerOrderPaymentProofWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_upload_payment_proof_for_a_specific_invoice(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$buyer, $seller, $offer, $invoice] = $this->createOrderWithInvoice();

        $response = $this
            ->actingAs($buyer)
            ->post(route('buyer.orders.invoices.payment-proof.update', [$offer, $invoice]), [
                'return_to' => 'orders',
                'payment_proof_date' => now()->toDateString(),
                'payment_reference' => 'BANK-TRX-9981',
                'payment_notes' => 'Payment completed through main fleet banking account.',
                'payment_proof_document' => UploadedFile::fake()->create('bank-slip.pdf', 180, 'application/pdf'),
            ]);

        $response
            ->assertRedirect(route('buyer.orders'))
            ->assertSessionHas('success.code', 'payment-proof-uploaded');

        $offer->refresh();
        $invoice->refresh();

        $this->assertSame(Offer::ORDER_STATUS_PAYMENT_PROOF_UPLOADED, $offer->order_workflow_status);
        $this->assertSame('BANK-TRX-9981', $invoice->payment_reference);
        $this->assertNotNull($invoice->payment_proof_document_path);
        Storage::disk('public')->assertExists($invoice->payment_proof_document_path);

        Notification::assertSentTo(
            $buyer,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($buyer, $offer): bool {
                $payload = $notification->toArray($buyer);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Payment Proof Uploaded'
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
                    && ($payload['title'] ?? null) === 'Buyer Payment Proof Uploaded'
                    && ($payload['action_url'] ?? null) === route('seller.orders.show', $offer);
            }
        );

        $this->actingAs($buyer)
            ->get(route('buyer.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/Dashboard/OrderDetail')
                ->where('order.order_workflow_status', Offer::ORDER_STATUS_PAYMENT_PROOF_UPLOADED)
                ->where('order.order_workflow_status_label', 'Payment Proof Uploaded')
                ->where('order.invoices.0.payment_reference', 'BANK-TRX-9981')
                ->where('order.invoices.0.payment_proof_document.name', 'bank-slip.pdf')
            );

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Supplier/Dashboard/OrderDetail')
                ->where('order.order_workflow_status', Offer::ORDER_STATUS_PAYMENT_PROOF_UPLOADED)
                ->where('order.order_workflow_status_label', 'Payment Proof Uploaded')
                ->where('order.invoices.0.payment_reference', 'BANK-TRX-9981')
                ->where('order.invoices.0.payment_proof_document.name', 'bank-slip.pdf')
            );
    }

    public function test_seller_can_confirm_payment_after_buyer_uploads_payment_proof(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$buyer, $seller, $offer, $invoice] = $this->createOrderWithInvoice([
            'payment_proof_date' => now()->toDateString(),
            'payment_reference' => 'BANK-TRX-9981',
            'payment_notes' => 'Buyer uploaded bank slip.',
            'payment_proof_document_disk' => 'public',
            'payment_proof_document_path' => 'offers/test/payment-proof.pdf',
            'payment_proof_document_name' => 'payment-proof.pdf',
            'payment_proof_document_mime_type' => 'application/pdf',
            'payment_proof_document_size' => 1024,
        ]);

        Storage::disk('public')->put('offers/test/payment-proof.pdf', 'proof');

        $response = $this
            ->actingAs($seller)
            ->post(route('seller.orders.invoices.payment-confirm.store', [$offer, $invoice]), [
                'return_to' => 'orders',
            ]);

        $response
            ->assertRedirect(route('seller.orders'))
            ->assertSessionHas('success.code', 'payment-confirmed');

        $offer->refresh();
        $invoice->refresh();

        $this->assertSame(Offer::ORDER_STATUS_COMPLETED, $offer->order_workflow_status);
        $this->assertNotNull($invoice->payment_confirmed_at);

        Notification::assertSentTo(
            $buyer,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($buyer, $offer): bool {
                $payload = $notification->toArray($buyer);

                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($payload['title'] ?? null) === 'Payment Receipt Confirmed'
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
                    && ($payload['title'] ?? null) === 'Payment Receipt Confirmed'
                    && ($payload['action_url'] ?? null) === route('seller.orders.show', $offer);
            }
        );

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Supplier/Dashboard/OrderDetail')
                ->where('order.order_workflow_status', Offer::ORDER_STATUS_COMPLETED)
                ->where('order.order_workflow_status_label', 'Completed')
            );
    }

    public function test_seller_can_confirm_payment_when_buyer_payment_metadata_exists_without_file_path(): void
    {
        Storage::fake('public');

        [$buyer, $seller, $offer, $invoice] = $this->createOrderWithInvoice([
            'payment_proof_date' => now()->toDateString(),
            'payment_reference' => 'BANK-TRX-METADATA-001',
            'payment_notes' => 'Buyer uploaded payment metadata but the legacy file path is missing.',
            'payment_proof_document_disk' => null,
            'payment_proof_document_path' => null,
            'payment_proof_document_name' => null,
            'payment_proof_document_mime_type' => null,
            'payment_proof_document_size' => null,
        ]);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Supplier/Dashboard/OrderDetail')
                ->where('order.invoices.0.status', OfferInvoice::STATUS_PAYMENT_PROOF_UPLOADED)
                ->where('order.invoices.0.status_label', 'Payment Proof Uploaded')
                ->where('order.invoices.0.can_confirm_payment', true)
            );

        $response = $this
            ->actingAs($seller)
            ->post(route('seller.orders.invoices.payment-confirm.store', [$offer, $invoice]), [
                'return_to' => 'orders',
            ]);

        $response
            ->assertRedirect(route('seller.orders'))
            ->assertSessionHas('success.code', 'payment-confirmed');

        $offer->refresh();
        $invoice->refresh();

        $this->assertSame(Offer::ORDER_STATUS_COMPLETED, $offer->order_workflow_status);
        $this->assertNotNull($invoice->payment_confirmed_at);
    }

    public function test_seller_gets_redirect_instead_of_403_when_payment_proof_is_missing(): void
    {
        Storage::fake('public');

        [$buyer, $seller, $offer, $invoice] = $this->createOrderWithInvoice();

        $response = $this
            ->actingAs($seller)
            ->post(route('seller.orders.invoices.payment-confirm.store', [$offer, $invoice]), [
                'return_to' => 'orders',
            ]);

        $response
            ->assertRedirect(route('seller.orders'))
            ->assertSessionHas('error.code', 'payment-proof-required');

        $offer->refresh();
        $invoice->refresh();

        $this->assertNull($invoice->payment_confirmed_at);
        $this->assertSame(Offer::ORDER_STATUS_INVOICE_UPLOADED, $offer->order_workflow_status);
    }

    private function createOrderWithInvoice(array $invoiceOverrides = []): array
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createReadySeller('Atlas Marine', 'TR', 'Turkey', 'Istanbul', 'TRIST');

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-PAYMENT-PROOF-001',
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
            'general_notes' => 'Buyer payment proof workflow test.',
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
            'submitted_at' => now(),
            'order_workflow_status' => Offer::ORDER_STATUS_INVOICE_UPLOADED,
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

        $invoice = OfferInvoice::query()->create(array_merge([
            'offer_id' => $offer->id,
            'currency' => 'USD',
            'invoice_number' => 'INV-2026-001',
            'invoice_date' => now()->toDateString(),
            'invoice_amount' => 145.50,
            'invoice_notes' => 'Invoice already uploaded by supplier.',
            'invoice_document_disk' => 'public',
            'invoice_document_path' => 'offers/test/invoice.pdf',
            'invoice_document_name' => 'invoice.pdf',
            'invoice_document_mime_type' => 'application/pdf',
            'invoice_document_size' => 1200,
        ], $invoiceOverrides));

        Storage::disk('public')->put('offers/test/invoice.pdf', 'invoice');

        return [$buyer, $seller, $offer, $invoice];
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
            'registration_number' => "{$countryCode}-PAYMENT-PROOF-001",
            'company_registration_documents' => [['path' => 'rfqs/10/items/21/fJ1lllxW2nFcmHe9CNEwUXsT7fcs5XmaqNJ2aztC.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        return $seller;
    }
}
