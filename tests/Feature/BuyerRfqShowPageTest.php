<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqSupplierRecipient;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BuyerRfqShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_show_page_includes_compare_data_top_level_attachment_mime_type_and_selected_recipients(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'Atlas Marine Service',
        ]);
        $port = $this->createActivePort();

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SHOW-SERVICE-001',
            'company_name' => 'Northwind Buyer',
            'ship_name' => 'MV Horizon',
            'request_type' => 'service_request',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
            'country_names' => [$port->country_name],
            'ports_by_country' => [
                $port->country_name => [
                    ['id' => $port->id, 'name' => $port->port_name, 'unlocode' => $port->unlocode],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Service request note.',
            'service_title' => 'Fresh Water Supply',
            'service_description' => 'Fresh water supply attendance requested at berth.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $storedFile = UploadedFile::fake()->create('scope-sheet.pdf', 24, 'application/pdf');
        $path = $storedFile->store("rfqs/{$rfq->id}/attachments", 'public');

        $rfq->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'scope-sheet.pdf',
            'mime_type' => 'application/pdf',
            'size' => $storedFile->getSize(),
        ]);

        RfqSupplierRecipient::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'company_name' => $seller->company_name,
            'category_name' => 'Deck Stores',
            'subcategory_name' => 'Rope Supply',
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
        ]);

        $offer = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'service_request',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'including_tax' => true,
            'including_packing' => true,
            'including_freight' => true,
            'including_mobilization' => true,
            'total_offer_amount' => 1500,
            'grand_total' => 1500,
            'submitted_at' => now(),
        ]);

        $offerFile = UploadedFile::fake()->create('supplier-offer.pdf', 12, 'application/pdf');
        $offerPath = $offerFile->store("offers/{$offer->id}/service", 'public');

        $offer->attachments()->create([
            'disk' => 'public',
            'path' => $offerPath,
            'original_name' => 'supplier-offer.pdf',
            'mime_type' => 'application/pdf',
            'size' => $offerFile->getSize(),
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.rfqs.show', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Show')
                ->where('rfq.reference_no', 'RFQ-SHOW-SERVICE-001')
                ->where('rfq.offers_count', 1)
                ->where('rfq.compare_url', route('buyer.rfqs.compare', $rfq))
                ->where('rfq.offers.0.id', $offer->id)
                ->where('rfq.offers.0.seller.company_name', 'Atlas Marine Service')
                ->where('rfq.offers.0.attachments.0.name', 'supplier-offer.pdf')
                ->where('rfq.offers.0.attachments.0.mime_type', 'application/pdf')
                ->where('rfq.attachments.0.name', 'scope-sheet.pdf')
                ->where('rfq.attachments.0.mime_type', 'application/pdf')
                ->where('rfq.recipients.0.company_name', 'Atlas Marine Service')
                ->where('rfq.recipients.0.category_name', 'Deck Stores')
                ->where('rfq.recipients.0.subcategory_name', 'Rope Supply')
                ->where('rfq.recipients.0.country_name', $port->country_name)
                ->where('rfq.recipients.0.port_name', $port->port_name)
            );
    }

    public function test_buyer_show_page_includes_submitted_spare_parts_offer_items_for_supplier_offer_view(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'Blue Anchor Parts',
        ]);
        $port = $this->createActivePort();

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SHOW-SPARE-OFFER-001',
            'company_name' => 'Northwind Buyer',
            'ship_name' => 'MV Horizon',
            'request_type' => 'spare_parts',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
            'country_names' => [$port->country_name],
            'ports_by_country' => [
                $port->country_name => [
                    ['id' => $port->id, 'name' => $port->port_name, 'unlocode' => $port->unlocode],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Spare parts offer section test.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $item = RfqItem::query()->create([
            'rfq_id' => $rfq->id,
            'line_no' => 1,
            'product_name' => 'Ball Valve',
            'part_no' => 'BV-1001',
            'quantity' => 6,
            'unit' => 'PCS',
            'quality' => 'genuine',
        ]);

        $requestItemFile = UploadedFile::fake()->create('customer-spec.pdf', 10, 'application/pdf');
        $requestItemPath = $requestItemFile->store("rfqs/{$rfq->id}/items/{$item->id}", 'public');

        $item->attachments()->create([
            'disk' => 'public',
            'path' => $requestItemPath,
            'original_name' => 'customer-spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => $requestItemFile->getSize(),
        ]);

        $offer = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'spare_parts',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'including_tax' => true,
            'including_packing' => true,
            'including_freight' => true,
            'total_offer_amount' => 60,
            'grand_total' => 60,
            'submitted_at' => now(),
        ]);

        $offerItem = $offer->items()->create([
            'rfq_item_id' => $item->id,
            'line_no' => 1,
            'offer_qty' => 6,
            'unit_price' => 10,
            'line_total' => 60,
            'delivery_time' => '7 days',
            'quality' => 'original',
            'manufacturer' => 'ABB Marine',
            'remarks' => 'Ready ex stock.',
        ]);

        $offerItemFile = UploadedFile::fake()->create('ball-valve-spec.pdf', 10, 'application/pdf');
        $offerItemPath = $offerItemFile->store("offers/{$offer->id}/items/{$offerItem->id}", 'public');

        $offerItem->attachments()->create([
            'disk' => 'public',
            'path' => $offerItemPath,
            'original_name' => 'ball-valve-spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => $offerItemFile->getSize(),
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.rfqs.show', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Show')
                ->where('rfq.items.0.attachments.0.name', 'customer-spec.pdf')
                ->where('rfq.items.0.attachments.0.mime_type', 'application/pdf')
                ->where('rfq.offers.0.id', $offer->id)
                ->where('rfq.offers.0.seller.company_name', 'Blue Anchor Parts')
                ->where('rfq.offers.0.items.0.rfq_item_id', $item->id)
                ->where('rfq.offers.0.items.0.manufacturer', 'ABB Marine')
                ->where('rfq.offers.0.items.0.attachments.0.name', 'ball-valve-spec.pdf')
                ->where('rfq.offers.0.items.0.attachments.0.mime_type', 'application/pdf')
            );
    }

    public function test_buyer_show_page_includes_spare_parts_item_attachment_mime_type(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => 'buyer']);
        $port = $this->createActivePort();
        $brand = Brand::query()->create([
            'name' => 'MAN',
            'slug' => 'man',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Pumps',
            'slug' => 'pumps',
            'has_subcategories' => true,
            'is_active' => true,
        ]);
        $subcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Pump Repair Kit',
            'slug' => 'pump-repair-kit',
            'is_active' => true,
        ]);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SHOW-SPARE-001',
            'company_name' => 'Northwind Buyer',
            'ship_name' => 'MV Horizon',
            'request_type' => 'spare_parts',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
            'country_names' => [$port->country_name],
            'category_ids' => [$category->id],
            'subcategory_ids' => [$subcategory->id],
            'brand_ids' => [$brand->id],
            'ports_by_country' => [
                $port->country_name => [
                    ['id' => $port->id, 'name' => $port->port_name, 'unlocode' => $port->unlocode],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_DRAFT,
            'general_notes' => 'Spare parts draft note.',
            'items_count' => 1,
        ]);

        $item = RfqItem::query()->create([
            'rfq_id' => $rfq->id,
            'line_no' => 1,
            'product_name' => 'Pump overhaul kit',
            'part_no' => 'PK-100',
            'quantity' => 2,
            'unit' => 'PCS',
            'manufacturer' => 'MAN',
            'model_type' => 'B&W',
            'serial_number' => 'SER-100',
            'catalog_code' => 'CAT-100',
            'rob' => 1,
            'drawing_number' => 'DWG-100',
            'quality' => 'genuine',
            'comments' => 'Need quotation and lead time.',
        ]);

        $storedFile = UploadedFile::fake()->image('pump-photo.jpg');
        $path = $storedFile->store("rfqs/{$rfq->id}/items/{$item->id}", 'public');

        $item->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'pump-photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $storedFile->getSize(),
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.rfqs.show', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Show')
                ->where('rfq.items.0.product_name', 'Pump overhaul kit')
                ->where('rfq.selected_categories.0', 'Pumps')
                ->where('rfq.selected_subcategories.0', 'Pump Repair Kit')
                ->where('rfq.selected_brands.0', 'MAN')
                ->where('rfq.items.0.attachments.0.name', 'pump-photo.jpg')
                ->where('rfq.items.0.attachments.0.mime_type', 'image/jpeg')
            );
    }

    public function test_only_owner_buyer_can_open_buyer_rfq_show_page(): void
    {
        $owner = User::factory()->create(['role' => 'buyer']);
        $otherBuyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create(['role' => 'seller']);
        $admin = User::factory()->create(['role' => 'admin']);
        $port = $this->createActivePort();

        $rfq = Rfq::query()->create([
            'buyer_id' => $owner->id,
            'reference_no' => 'RFQ-SHOW-ACCESS-001',
            'company_name' => 'Northwind Buyer',
            'ship_name' => 'MV Horizon',
            'request_type' => 'service_request',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
            'country_names' => [$port->country_name],
            'ports_by_country' => [
                $port->country_name => [
                    ['id' => $port->id, 'name' => $port->port_name, 'unlocode' => $port->unlocode],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Access control test.',
            'service_title' => 'Technician Attendance',
            'service_description' => 'Technician attendance requested.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('buyer.rfqs.show', $rfq))
            ->assertOk();

        $this->actingAs($otherBuyer)
            ->get(route('buyer.rfqs.show', $rfq))
            ->assertForbidden();

        $this->actingAs($seller)
            ->get(route('buyer.rfqs.show', $rfq))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('buyer.rfqs.show', $rfq))
            ->assertForbidden();
    }

    public function test_buyer_show_page_filters_offers_to_selected_supplier_order_when_offer_query_is_present(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $sellerOne = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'Atlas Marine Service',
        ]);
        $sellerTwo = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'Ocean Parts Supply',
        ]);
        $port = $this->createActivePort();

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SHOW-AWARD-FILTER-001',
            'company_name' => 'Northwind Buyer',
            'ship_name' => 'MV Horizon',
            'request_type' => 'spare_parts',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
            'country_names' => [$port->country_name],
            'ports_by_country' => [
                $port->country_name => [
                    ['id' => $port->id, 'name' => $port->port_name, 'unlocode' => $port->unlocode],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_CLOSED,
            'general_notes' => 'Award filter test.',
            'items_count' => 2,
            'submitted_at' => now(),
        ]);

        $itemOne = RfqItem::query()->create([
            'rfq_id' => $rfq->id,
            'line_no' => 1,
            'product_name' => 'Cylinder liner',
            'part_no' => 'CL-001',
            'quantity' => 3,
            'unit' => 'PCS',
            'quality' => 'genuine',
        ]);

        $itemTwo = RfqItem::query()->create([
            'rfq_id' => $rfq->id,
            'line_no' => 2,
            'product_name' => 'Piston ring set',
            'part_no' => 'PR-002',
            'quantity' => 4,
            'unit' => 'SET',
            'quality' => 'oem',
        ]);

        $offerOne = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $sellerOne->id,
            'request_type' => 'spare_parts',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $offerTwo = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $sellerTwo->id,
            'request_type' => 'spare_parts',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $offerOneItem = $offerOne->items()->create([
            'rfq_item_id' => $itemOne->id,
            'line_no' => 1,
            'offer_qty' => 3,
            'unit_price' => 10,
            'line_total' => 30,
        ]);

        $offerTwoItem = $offerTwo->items()->create([
            'rfq_item_id' => $itemTwo->id,
            'line_no' => 2,
            'offer_qty' => 4,
            'unit_price' => 20,
            'line_total' => 80,
        ]);

        OfferAward::query()->create([
            'rfq_id' => $rfq->id,
            'buyer_id' => $buyer->id,
            'offer_id' => $offerOne->id,
            'offer_item_id' => $offerOneItem->id,
            'rfq_item_id' => $itemOne->id,
            'request_type' => 'spare_parts',
            'status' => OfferAward::STATUS_CONFIRMED,
            'awarded_quantity' => 3,
            'buyer_note' => 'Atlas selected.',
            'confirmed_at' => now(),
        ]);

        OfferAward::query()->create([
            'rfq_id' => $rfq->id,
            'buyer_id' => $buyer->id,
            'offer_id' => $offerTwo->id,
            'offer_item_id' => $offerTwoItem->id,
            'rfq_item_id' => $itemTwo->id,
            'request_type' => 'spare_parts',
            'status' => OfferAward::STATUS_CONFIRMED,
            'awarded_quantity' => 4,
            'buyer_note' => 'Ocean selected.',
            'confirmed_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.rfqs.show', [
                'rfq' => $rfq,
                'offer' => $offerOne->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Show')
                ->where('rfq.selected_order_offer_id', $offerOne->id)
                ->where('rfq.offers', function ($offers) use ($offerOne) {
                    $offers = collect($offers)->values();

                    return $offers->count() === 1
                        && (int) ($offers->first()['id'] ?? 0) === $offerOne->id;
                })
            );
    }

    public function test_buyer_rfq_switches_to_completed_and_compare_redirects_back_to_view_when_all_confirmed_orders_are_finished(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'Atlas Marine Service',
        ]);
        $port = $this->createActivePort();

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-COMPLETED-001',
            'company_name' => 'Northwind Buyer',
            'ship_name' => 'MV Horizon',
            'request_type' => 'spare_parts',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
            'country_names' => [$port->country_name],
            'ports_by_country' => [
                $port->country_name => [
                    ['id' => $port->id, 'name' => $port->port_name, 'unlocode' => $port->unlocode],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_CLOSED,
            'general_notes' => 'Completed RFQ flow test.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $item = RfqItem::query()->create([
            'rfq_id' => $rfq->id,
            'line_no' => 1,
            'product_name' => 'Cylinder liner',
            'part_no' => 'CL-001',
            'quantity' => 2,
            'unit' => 'PCS',
            'quality' => 'genuine',
        ]);

        $offer = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'spare_parts',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'order_workflow_status' => Offer::ORDER_STATUS_PAYMENT_CONFIRMED,
            'billing_company_name' => 'Northwind Buyer Ltd.',
            'billing_address' => 'Port Avenue 10',
            'billing_tax_id' => 'TR-001',
            'billing_contact_name' => 'Ayse Demir',
            'billing_contact_email' => 'billing@northwind.test',
            'billing_contact_phone' => '+90 212 555 0101',
            'delivery_target_type' => 'vessel',
            'delivery_country' => 'Albania',
            'delivery_port' => 'Durres',
            'delivery_address' => 'MV Horizon / Durres',
            'delivery_contact_name' => 'Chief Officer',
            'delivery_contact_email' => 'chief@northwind.test',
            'delivery_contact_phone' => '+90 212 555 0102',
            'delivery_required_date' => now()->addDays(7)->toDateString(),
            'submitted_at' => now(),
        ]);

        $offerItem = $offer->items()->create([
            'rfq_item_id' => $item->id,
            'line_no' => 1,
            'offer_qty' => 2,
            'unit_price' => 100,
            'line_total' => 200,
            'delivery_time' => '10',
            'quality' => 'genuine',
            'manufacturer' => 'Atlas Marine',
            'remarks' => 'Ready to supply.',
        ]);

        OfferAward::query()->create([
            'rfq_id' => $rfq->id,
            'buyer_id' => $buyer->id,
            'offer_id' => $offer->id,
            'offer_item_id' => $offerItem->id,
            'rfq_item_id' => $item->id,
            'request_type' => 'spare_parts',
            'status' => OfferAward::STATUS_CONFIRMED,
            'awarded_quantity' => 2,
            'buyer_note' => 'Proceed with this line.',
            'confirmed_at' => now(),
        ]);

        $offer->invoices()->create([
            'currency' => 'USD',
            'invoice_number' => 'INV-COMPLETE-001',
            'invoice_date' => now()->toDateString(),
            'invoice_amount' => 200,
            'invoice_notes' => 'Final invoice',
            'invoice_document_disk' => 'public',
            'invoice_document_path' => 'offers/test/completed-invoice.pdf',
            'invoice_document_name' => 'completed-invoice.pdf',
            'invoice_document_mime_type' => 'application/pdf',
            'invoice_document_size' => 1024,
            'payment_proof_date' => now()->toDateString(),
            'payment_reference' => 'BANK-TRX-COMPLETE-001',
            'payment_notes' => 'Paid in full.',
            'payment_proof_document_disk' => 'public',
            'payment_proof_document_path' => 'offers/test/completed-proof.pdf',
            'payment_proof_document_name' => 'completed-proof.pdf',
            'payment_proof_document_mime_type' => 'application/pdf',
            'payment_proof_document_size' => 1024,
            'payment_confirmed_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.rfqs.show', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Show')
                ->where('rfq.status', 'completed')
                ->where('rfq.compare_url', null)
            );

        $this->actingAs($buyer)
            ->get(route('buyer.rfqs.compare', $rfq))
            ->assertRedirect(route('buyer.rfqs.show', $rfq));
    }

    private function createActivePort(): Port
    {
        return Port::query()->create([
            'unlocode' => 'ALDRZ',
            'country_code' => 'AL',
            'location_code' => 'DRZ',
            'country_name' => 'Albania',
            'port_name' => 'Durres',
            'is_active' => true,
        ]);
    }
}
