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
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminDashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_admin_dashboard_rfq_and_order_pages(): void
    {
        [$admin, $rfq, $offer, $seller] = $this->createAdminScenario();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/Dashboard')
                ->where('activeTab', 'businesses')
                ->where('dashboard.navigation.rfqs_count', 1)
                ->where('dashboard.navigation.orders_count', 1)
            );

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['tab' => 'users']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/Dashboard')
                ->where('activeTab', 'users')
            );

        $this->actingAs($admin)
            ->get(route('admin.rfqs'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/Rfqs')
                ->where('rfqsTable.data.0.reference_no', $rfq->reference_no)
                ->where('rfqsTable.data.0.company_name', 'Northwind Buyer')
                ->where('rfqsTable.data.0.is_private_request', true)
            );

        $this->actingAs($admin)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/Orders')
                ->where('ordersTable.data.0.reference_no', $rfq->reference_no)
                ->where('ordersTable.data.0.supplier_name', $seller->company_name)
                ->where('ordersTable.data.0.offer_id', $offer->id)
            );

        $this->actingAs($admin)
            ->get(route('admin.rfqs.show', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/RfqDetail')
                ->where('rfq.reference_no', $rfq->reference_no)
                ->where('rfq.items.0.product_name', 'Fuel Pump')
                ->where('rfq.attachments.0.name', 'admin-rfq-scope.pdf')
                ->where('rfq.recipients.0.company_name', $seller->company_name)
                ->where('rfq.offers.0.seller.company_name', $seller->company_name)
                ->where('rfq.offers.0.items.0.manufacturer', 'Bosch Marine')
            );

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/OrderDetail')
                ->where('order.reference_no', $rfq->reference_no)
                ->where('order.offer_id', $offer->id)
                ->where('order.supplier_name', $seller->company_name)
                ->where('order.selected_items.0.product_name', 'Fuel Pump')
                ->where('order.rfq_show_url', route('admin.rfqs.show', $rfq))
            );
    }

    public function test_non_admin_users_cannot_open_admin_dashboard_rfq_or_order_pages(): void
    {
        [, $rfq, $offer] = $this->createAdminScenario();
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($buyer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.rfqs'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.orders'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.rfqs.show', $rfq))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.orders.show', $offer))->assertForbidden();

        $this->actingAs($seller)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.rfqs'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.orders'))->assertForbidden();
    }

    public function test_admin_rfq_list_exposes_management_actions_and_locked_edit_reason(): void
    {
        [$admin, $rfq] = $this->createAdminScenario();

        $this->actingAs($admin)
            ->get(route('admin.rfqs'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/Rfqs')
                ->where('rfqsTable.data.0.reference_no', $rfq->reference_no)
                ->where('rfqsTable.data.0.compare_url', route('admin.rfqs.compare', $rfq))
                ->where('rfqsTable.data.0.edit_url', route('admin.rfqs.edit', $rfq))
                ->where('rfqsTable.data.0.delete_url', route('admin.rfqs.destroy', $rfq))
                ->where('rfqsTable.data.0.can_edit', false)
                ->where('rfqsTable.data.0.edit_reason', 'confirmed_orders')
            );
    }

    public function test_admin_can_open_compare_and_general_only_edit_for_buyer_rfq(): void
    {
        [$admin, $rfq] = $this->createAdminScenario(false);

        $this->actingAs($admin)
            ->get(route('admin.rfqs.compare', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Compare')
                ->where('backUrl', route('admin.rfqs'))
                ->where('rfq.reference_no', $rfq->reference_no)
                ->where('rfq.award_save_url', route('admin.rfqs.awards.store', $rfq))
            );

        $this->actingAs($admin)
            ->get(route('admin.rfqs.edit', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Create/Page')
                ->where('mode', 'edit')
                ->where('editPolicy.can_edit', true)
                ->where('editPolicy.general_only', true)
                ->where('editPolicy.reason', 'offers_received')
                ->where('actionUrl', route('admin.rfqs.update', $rfq))
                ->where('backUrl', route('admin.rfqs'))
            );
    }

    public function test_admin_can_confirm_awards_without_overwriting_original_buyer(): void
    {
        [$admin, $rfq, $offer] = $this->createAdminScenario(false);
        $offerItem = $offer->items()->firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('admin.rfqs.awards.store', $rfq), [
                'intent' => 'confirm',
                'spare_item_awards' => [[
                    'offer_item_id' => $offerItem->id,
                    'awarded_quantity' => 4,
                    'buyer_note' => 'Admin confirmed on behalf of buyer.',
                ]],
            ]);

        $response->assertRedirect(route('admin.rfqs'));
        $response->assertSessionHas('success', 'award-confirmed');

        $award = OfferAward::query()->where('rfq_id', $rfq->id)->firstOrFail();

        $this->assertSame($rfq->buyer_id, $award->buyer_id);
        $this->assertSame(OfferAward::STATUS_CONFIRMED, $award->status);
        $this->assertSame(Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING, $offer->fresh()->order_workflow_status);
        $this->assertSame(Rfq::STATUS_CLOSED, $rfq->fresh()->status);
    }

    public function test_admin_can_force_delete_rfq_with_offers_and_awards(): void
    {
        [$admin, $rfq, $offer] = $this->createAdminScenario();

        $response = $this->actingAs($admin)
            ->delete(route('admin.rfqs.destroy', $rfq));

        $response->assertRedirect(route('admin.rfqs'));
        $response->assertSessionHas('success', 'rfq-deleted');

        $this->assertDatabaseMissing('rfqs', ['id' => $rfq->id]);
        $this->assertDatabaseMissing('offers', ['id' => $offer->id]);
        $this->assertSame(0, OfferAward::query()->where('rfq_id', $rfq->id)->count());
        $this->assertSame(0, RfqSupplierRecipient::query()->where('rfq_id', $rfq->id)->count());
    }

    public function test_admin_rfq_and_order_details_expose_full_management_actions(): void
    {
        [$admin, $rfq, $offer, $seller] = $this->createAdminScenario();

        $this->actingAs($admin)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/Orders')
                ->where('ordersTable.data.0.modal_url', route('admin.orders.modal', $offer))
                ->where('ordersTable.data.0.can_edit_order_information', true)
                ->where('ordersTable.data.0.can_manage_invoices', false)
                ->where('ordersTable.data.0.can_add_invoice', true)
                ->where('ordersTable.data.0.has_invoices', false)
            );

        $this->actingAs($admin)
            ->get(route('admin.rfqs.show', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/RfqDetail')
                ->where('rfq.compare_url', route('admin.rfqs.compare', $rfq))
                ->where('rfq.edit_url', route('admin.rfqs.edit', $rfq))
                ->where('rfq.delete_url', route('admin.rfqs.destroy', $rfq))
                ->where('rfq.can_edit', false)
                ->where('rfq.can_delete', true)
                ->where('rfq.edit_reason', 'confirmed_orders')
            );

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/OrderDetail')
                ->where('order.update_order_information_url', route('admin.orders.information.update', $offer))
                ->where('order.create_invoice_url', route('admin.orders.invoices.store', $offer))
                ->where('order.can_edit_order_information', true)
                ->where('order.can_manage_invoices', false)
                ->where('order.can_add_invoice', true)
                ->where('order.supplier_name', $seller->company_name)
            );

        $this->actingAs($admin)
            ->get(route('admin.orders.modal', $offer))
            ->assertOk()
            ->assertJsonPath('order.update_order_information_url', route('admin.orders.information.update', $offer))
            ->assertJsonPath('order.create_invoice_url', route('admin.orders.invoices.store', $offer))
            ->assertJsonPath('order.can_edit_order_information', true)
            ->assertJsonPath('order.can_manage_invoices', false)
            ->assertJsonPath('order.can_add_invoice', true)
            ->assertJsonPath('order.rfq_show_url', route('admin.rfqs.show', $rfq));
    }

    public function test_admin_can_complete_full_order_workflow_from_order_information_to_payment_confirmation(): void
    {
        Storage::fake('public');

        [$admin, $rfq, $offer] = $this->createAdminScenario();

        $this->actingAs($admin)
            ->put(route('admin.orders.information.update', $offer), [
                'return_to' => 'orders',
                'billing_company_name' => 'Northwind Buyer Accounts',
                'billing_address' => 'Liman Caddesi 18, Istanbul',
                'billing_tax_id' => 'TR-778899',
                'billing_contact_name' => 'Ayse Demir',
                'billing_contact_email' => 'billing@northwind.test',
                'billing_contact_phone' => '+90 212 555 0101',
                'delivery_target_type' => 'vessel',
                'delivery_country' => 'Turkey',
                'delivery_port' => 'Istanbul',
                'delivery_address' => 'MV Horizon / Istanbul Anchorage',
                'delivery_contact_name' => 'Chief Officer',
                'delivery_contact_email' => 'chief.officer@northwind.test',
                'delivery_contact_phone' => '+90 212 555 0102',
                'delivery_required_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirect(route('admin.orders'))
            ->assertSessionHas('success.code', 'order-information-saved');

        $offer->refresh();
        $this->assertSame(Offer::ORDER_STATUS_INVOICE_PENDING, $offer->order_workflow_status);

        $this->actingAs($admin)
            ->post(route('admin.orders.invoices.store', $offer), [
                'return_to' => 'orders',
                'invoice_number' => 'INV-ADMIN-001',
                'invoice_date' => now()->toDateString(),
                'invoice_amount' => '465.00',
                'invoice_notes' => 'Admin-created invoice for the confirmed order.',
                'invoice_document' => UploadedFile::fake()->create('admin-invoice.pdf', 220, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.orders'))
            ->assertSessionHas('success.code', 'invoice-added');

        $offer->refresh();
        $invoice = $offer->invoices()->firstOrFail();

        $this->assertSame(Offer::ORDER_STATUS_INVOICE_UPLOADED, $offer->order_workflow_status);
        $this->assertSame('INV-ADMIN-001', $invoice->invoice_number);
        Storage::disk('public')->assertExists($invoice->invoice_document_path);

        $this->actingAs($admin)
            ->post(route('admin.orders.invoices.payment-proof.update', [$offer, $invoice]), [
                'return_to' => 'orders',
                'payment_proof_date' => now()->toDateString(),
                'payment_reference' => 'ADMIN-BANK-001',
                'payment_notes' => 'Admin uploaded the buyer payment proof.',
                'payment_proof_document' => UploadedFile::fake()->create('admin-payment-proof.pdf', 180, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.orders'))
            ->assertSessionHas('success.code', 'payment-proof-uploaded');

        $offer->refresh();
        $invoice->refresh();

        $this->assertSame(Offer::ORDER_STATUS_PAYMENT_PROOF_UPLOADED, $offer->order_workflow_status);
        $this->assertSame('ADMIN-BANK-001', $invoice->payment_reference);
        Storage::disk('public')->assertExists($invoice->payment_proof_document_path);

        $this->actingAs($admin)
            ->post(route('admin.orders.invoices.payment-confirm.store', [$offer, $invoice]), [
                'return_to' => 'orders',
            ])
            ->assertRedirect(route('admin.orders'))
            ->assertSessionHas('success.code', 'payment-confirmed');

        $offer->refresh();
        $invoice->refresh();

        $this->assertSame(Offer::ORDER_STATUS_COMPLETED, $offer->order_workflow_status);
        $this->assertNotNull($invoice->payment_confirmed_at);

        $this->actingAs($admin)
            ->get(route('admin.orders.modal', $offer))
            ->assertOk()
            ->assertJsonPath('order.order_workflow_status', Offer::ORDER_STATUS_COMPLETED)
            ->assertJsonPath('order.can_edit_order_information', false)
            ->assertJsonPath('order.can_manage_invoices', false)
            ->assertJsonPath('order.invoices.0.invoice_number', 'INV-ADMIN-001')
            ->assertJsonPath('order.invoices.0.payment_reference', 'ADMIN-BANK-001');
    }

    public function test_admin_can_update_user_registration_fields_and_email_verification_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.test',
        ]);

        $user = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Initial Buyer',
            'email' => 'buyer@example.test',
            'company_name' => null,
            'country' => 'Turkey',
            'countries' => 'Turkey',
            'phone' => '+90 5550000000',
            'whatsapp_number' => null,
            'company_description' => null,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.profile.update', $user), [
                'name' => 'Updated Buyer',
                'email' => 'updated-buyer@example.test',
                'role' => 'buyer',
                'company_name' => 'Updated Buyer Co',
                'country' => 'Greece',
                'phone' => '+30 2100000000',
                'whatsapp_number' => '+30 6900000000',
                'company_description' => 'Updated registration details',
                'email_verified' => true,
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Updated Buyer', $user->name);
        $this->assertSame('updated-buyer@example.test', $user->email);
        $this->assertSame('Updated Buyer Co', $user->company_name);
        $this->assertSame('Greece', $user->country);
        $this->assertSame('Greece', $user->countries);
        $this->assertSame('+30 2100000000', $user->phone);
        $this->assertSame('+30 6900000000', $user->whatsapp_number);
        $this->assertSame('Updated registration details', $user->company_description);
        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($admin)
            ->patch(route('admin.users.profile.update', $user), [
                'name' => 'Updated Buyer',
                'email' => 'updated-buyer@example.test',
                'role' => 'buyer',
                'company_name' => 'Updated Buyer Co',
                'country' => 'Greece',
                'phone' => '+30 2100000000',
                'whatsapp_number' => '+30 6900000000',
                'company_description' => 'Updated registration details',
                'email_verified' => false,
            ])
            ->assertRedirect();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    private function createAdminScenario(bool $confirmedAward = true): array
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
        ]);

        $buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Northwind Buyer',
            'company_name' => 'Northwind Buyer',
        ]);

        $seller = $this->createReadySeller('Atlas Marine Supply', 'TR', 'Turkey', 'Istanbul', 'TRIST');

        $category = Category::query()->create([
            'name' => 'Pumps',
            'slug' => 'pumps',
            'has_subcategories' => true,
            'is_active' => true,
        ]);

        $subcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Fuel Pumps',
            'slug' => 'fuel-pumps',
            'is_active' => true,
        ]);

        $brand = Brand::query()->create([
            'name' => 'Bosch',
            'slug' => 'bosch',
            'is_active' => true,
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

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-ADMIN-001',
            'company_name' => 'Northwind Buyer',
            'ship_name' => 'MV Horizon',
            'request_type' => 'spare_parts',
            'visibility_scope' => Rfq::VISIBILITY_PRIVATE_SUPPLIER,
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
            'country_names' => ['Turkey'],
            'ports_by_country' => [
                'Turkey' => [
                    ['id' => $port->id, 'name' => 'Istanbul', 'unlocode' => 'TRIST'],
                ],
            ],
            'category_ids' => [$category->id],
            'subcategory_ids' => [$subcategory->id],
            'brand_ids' => [$brand->id],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'USD',
            'priority' => 'high',
            'status' => $confirmedAward ? 'award_confirmed' : Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Admin RFQ detail coverage',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $item = RfqItem::query()->create([
            'rfq_id' => $rfq->id,
            'line_no' => 1,
            'product_name' => 'Fuel Pump',
            'part_no' => 'FP-100',
            'manufacturer' => 'MAN Energy',
            'model_type' => 'MK-II',
            'catalog_code' => 'CAT-100',
            'serial_number' => 'SER-100',
            'drawing_number' => 'DRW-100',
            'quantity' => 4,
            'unit' => 'PCS',
            'rob' => 1,
            'quality' => 'genuine',
            'comments' => 'Urgent replacement required.',
        ]);

        $rfq->attachments()->create([
            'disk' => 'public',
            'path' => 'rfqs/admin/rfq-scope.pdf',
            'original_name' => 'admin-rfq-scope.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1400,
        ]);

        $item->attachments()->create([
            'disk' => 'public',
            'path' => 'rfqs/admin/item-spec.pdf',
            'original_name' => 'admin-item-spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1200,
        ]);

        RfqSupplierRecipient::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'company_name' => $seller->company_name,
            'category_name' => 'Pumps',
            'subcategory_name' => 'Fuel Pumps',
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
        ]);

        $offer = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'spare_parts',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'including_tax' => true,
            'including_packing' => false,
            'packing_cost' => 25,
            'including_freight' => false,
            'freight_cost' => 40,
            'total_offer_amount' => 400,
            'grand_total' => 465,
            'award_scope_policy' => Offer::AWARD_SCOPE_PARTIAL_ALLOWED,
            'payment_order_confirmation' => 30,
            'payment_before_shipment' => 70,
            'other_payment_terms' => 'Balance after confirmation',
            'delivery_terms' => 'FOB',
            'other_delivery_terms' => 'Ex stock Istanbul',
            'general_note' => 'Ready for dispatch.',
            'submitted_at' => now(),
        ]);

        $offer->attachments()->create([
            'disk' => 'public',
            'path' => 'offers/admin/offer-summary.pdf',
            'original_name' => 'admin-offer-summary.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1300,
        ]);

        $offerItem = $offer->items()->create([
            'rfq_item_id' => $item->id,
            'line_no' => 1,
            'offer_qty' => 4,
            'unit_price' => 100,
            'line_total' => 400,
            'delivery_time' => '7 days',
            'quality' => 'oem',
            'manufacturer' => 'Bosch Marine',
            'remarks' => 'Class-approved stock.',
        ]);

        $offerItem->attachments()->create([
            'disk' => 'public',
            'path' => 'offers/admin/offer-item-spec.pdf',
            'original_name' => 'admin-offer-item-spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1100,
        ]);

        if ($confirmedAward) {
            OfferAward::query()->create([
                'rfq_id' => $rfq->id,
                'buyer_id' => $buyer->id,
                'offer_id' => $offer->id,
                'offer_item_id' => $offerItem->id,
                'rfq_item_id' => $item->id,
                'request_type' => 'spare_parts',
                'status' => OfferAward::STATUS_CONFIRMED,
                'awarded_quantity' => 4,
                'buyer_note' => 'Approved for purchase.',
                'confirmed_at' => now(),
            ]);
        }

        return [$admin, $rfq, $offer, $seller];
    }

    private function createReadySeller(string $companyName, string $countryCode, string $countryName, string $city, string $unlocode): User
    {
        $port = Port::query()->firstOrCreate(
            ['unlocode' => $unlocode],
            [
                'country_code' => $countryCode,
                'location_code' => substr($unlocode, -3),
                'country_name' => $countryName,
                'port_name' => $city,
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
            'contact_email' => Str::slug($companyName).'-seller@example.test',
            'company_address_line' => "{$city} Harbor Center",
            'company_city' => $city,
            'company_overview' => str_repeat("{$companyName} provides certified marine support across major ports. ", 4),
            'company_logo_path' => 'offers/1/items/1/demo-logo.png',
            'service_category_ids' => [1],
            'service_country_codes' => [$countryCode],
            'registration_number' => "{$countryCode}-READY-".strtoupper(substr(md5($companyName), 0, 6)),
            'company_registration_documents' => [['path' => 'rfqs/1/items/1/company.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        return $seller;
    }
}
