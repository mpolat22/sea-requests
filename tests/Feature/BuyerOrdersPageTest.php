<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\Subcategory;
use App\Models\User;
use App\Support\SupplierServiceListingIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BuyerOrdersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_orders_page_shows_only_owned_supplier_orders(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $otherBuyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create(['role' => 'seller', 'company_name' => 'Atlas Marine']);
        $otherSeller = User::factory()->create(['role' => 'seller', 'company_name' => 'Ocean Parts']);

        [$rfq, $offer] = $this->createConfirmedServiceOrder($buyer, $seller, 'RFQ-BUYER-ORDER-001');
        [$foreignRfq] = $this->createConfirmedServiceOrder($otherBuyer, $otherSeller, 'RFQ-OTHER-ORDER-001');

        $this->actingAs($buyer)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee($rfq->reference_no)
            ->assertSee('Atlas Marine')
            ->assertSee('Order Information Pending')
            ->assertDontSee($foreignRfq->reference_no);
    }

    public function test_buyer_orders_page_points_each_row_to_filtered_supplier_order_view(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createPublicSeller('Atlas Marine');

        [$rfq, $offer] = $this->createConfirmedServiceOrder($buyer, $seller, 'RFQ-BUYER-ORDER-ROUTE-001');

        $this->actingAs($buyer)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/Dashboard/Orders')
                ->where('orders.0.offer_id', $offer->id)
                ->where('orders.0.order_workflow_status', Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING)
                ->where('orders.0.order_workflow_status_label', 'Order Information Pending')
                ->where('orders.0.show_url', route('buyer.orders.show', $offer))
                ->where('orders.0.modal_url', route('buyer.orders.modal', $offer))
                ->where('orders.0.has_invoices', false)
                ->where('orders.0.rfq_show_url', route('buyer.rfqs.show', [
                    'rfq' => $rfq,
                    'offer' => $offer->id,
                ]))
                ->where('orders.0.supplier_profile_url', route('services.show', [
                    'category' => 'repair-services',
                    'subcategory' => 'engine-overhaul',
                    'vendor' => 'atlas-marine-'.$seller->id,
                ]))
            );
    }

    public function test_buyer_orders_modal_endpoint_returns_full_order_payload_for_owned_order(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createPublicSeller('Atlas Marine');

        [$rfq, $offer] = $this->createConfirmedServiceOrder($buyer, $seller, 'RFQ-BUYER-ORDER-MODAL-001');

        $this->actingAs($buyer)
            ->getJson(route('buyer.orders.modal', $offer))
            ->assertOk()
            ->assertJsonPath('order.offer_id', $offer->id)
            ->assertJsonPath('order.reference_no', $rfq->reference_no)
            ->assertJsonPath('order.show_url', route('buyer.orders.show', $offer))
            ->assertJsonPath('order.update_order_information_url', route('buyer.orders.information.update', $offer))
            ->assertJsonPath('order.supplier_profile_url', route('services.show', [
                'category' => 'repair-services',
                'subcategory' => 'engine-overhaul',
                'vendor' => 'atlas-marine-'.$seller->id,
            ]));
    }

    public function test_buyer_orders_summary_uses_invoice_pending_after_order_information_is_complete(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createPublicSeller('Atlas Marine');

        [$rfq, $offer] = $this->createConfirmedServiceOrder($buyer, $seller, 'RFQ-BUYER-ORDER-INVOICE-001');

        $offer->forceFill([
            'billing_company_name' => 'Buyer Billing Co',
            'billing_address' => 'Istanbul / Turkey',
            'billing_contact_name' => 'Buyer Contact',
            'billing_contact_email' => 'buyer@example.test',
            'billing_contact_phone' => '+90 5550000000',
            'service_location_type' => 'on_board',
            'service_location' => 'MV Workflow',
            'service_contact_name' => 'Chief Engineer',
            'service_contact_email' => 'chief@example.test',
            'service_contact_phone' => '+90 5551111111',
            'service_required_date' => now()->addDays(3)->toDateString(),
        ])->save();

        $this->actingAs($buyer)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/Dashboard/Orders')
                ->where('orders.0.offer_id', $offer->id)
                ->where('orders.0.reference_no', $rfq->reference_no)
                ->where('orders.0.order_workflow_status', Offer::ORDER_STATUS_INVOICE_PENDING)
                ->where('orders.0.order_workflow_status_label', 'Invoice Pending')
                ->where('orders.0.can_edit_order_information', true)
            );
    }

    public function test_non_buyers_cannot_access_buyer_orders_page(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($seller)
            ->get(route('buyer.orders'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('buyer.orders'))
            ->assertForbidden();
    }

    private function createConfirmedServiceOrder(User $buyer, User $seller, string $referenceNo): array
    {
        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => $referenceNo,
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Workflow',
            'request_type' => 'service_request',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => 'Albania',
            'port_name' => 'Durres',
            'country_names' => ['Albania'],
            'ports_by_country' => [
                'Albania' => [
                    ['id' => 1, 'name' => 'Durres', 'unlocode' => 'ALDRZ'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => 'award_confirmed',
            'general_notes' => 'Order page test',
            'service_title' => 'Hydraulic Attendance',
            'service_description' => 'Service request details',
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
            'payment_order_confirmation' => 30,
            'payment_before_shipment' => 70,
            'submitted_at' => now(),
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
            'buyer_note' => 'Proceed with this supplier.',
            'confirmed_at' => now(),
        ]);

        return [$rfq, $offer];
    }

    private function createPublicSeller(string $companyName): User
    {
        $category = Category::query()->create([
            'name' => 'Repair Services',
            'slug' => 'repair-services',
            'has_subcategories' => true,
            'is_active' => true,
        ]);

        $subcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Engine Overhaul',
            'slug' => 'engine-overhaul',
            'is_active' => true,
        ]);

        $port = Port::query()->create([
            'country_code' => 'TR',
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
            'unlocode' => 'TRIST',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => $companyName,
            'country' => 'TR',
            'approval_status' => 'approved',
            'approved_at' => now(),
            'service_category_ids' => [$category->id],
            'service_subcategory_ids' => [$subcategory->id],
            'service_subcategories_by_category' => [(string) $category->id => [$subcategory->id]],
            'service_country_codes' => ['TR'],
            'company_overview' => 'Approved supplier profile.',
        ]);

        $seller->servicePorts()->sync([$port->id]);

        app(SupplierServiceListingIndex::class)->syncSeller($seller);

        return $seller;
    }
}
