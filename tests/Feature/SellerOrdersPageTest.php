<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SellerOrdersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_orders_page_shows_only_confirmed_orders_for_the_signed_in_supplier(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createReadySeller('Atlas Marine', 'TR', 'Istanbul', 'TRIST');
        $otherSeller = $this->createReadySeller('Ocean Parts', 'AE', 'Dubai', 'AEDXB');

        [$rfq, $offer] = $this->createConfirmedServiceOrder($buyer, $seller, 'RFQ-SELLER-ORDER-001');
        [$foreignRfq] = $this->createConfirmedServiceOrder($buyer, $otherSeller, 'RFQ-SELLER-ORDER-FOREIGN');

        $this->actingAs($seller)
            ->get(route('seller.orders'))
            ->assertOk()
            ->assertSee($rfq->reference_no)
            ->assertSee('Orders')
            ->assertSee('Order Information Pending')
            ->assertDontSee($foreignRfq->reference_no);

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $offer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Supplier/Dashboard/OrderDetail')
                ->where('order.reference_no', $rfq->reference_no)
                ->where('order.offer_id', $offer->id)
                ->where('order.order_workflow_status', Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING)
                ->where('order.order_workflow_status_label', 'Order Information Pending')
                ->where('order.show_url', route('seller.rfqs.show', $rfq))
                ->where('order.award_scope_policy', Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED)
                ->where('order.service_description', 'Service request details')
                ->where('order.request_attachments.0.name', 'seller-service-request.pdf')
                ->where('order.offer_attachments.0.name', 'seller-service-offer.pdf')
            );
    }

    public function test_non_sellers_cannot_access_seller_orders_page(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($buyer)
            ->get(route('seller.orders'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('seller.orders'))
            ->assertForbidden();
    }

    public function test_seller_orders_modal_endpoint_returns_full_order_payload_for_owned_order(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createReadySeller('Atlas Marine', 'TR', 'Istanbul', 'TRIST');

        [$rfq, $offer] = $this->createConfirmedServiceOrder($buyer, $seller, 'RFQ-SELLER-ORDER-MODAL-001');

        $this->actingAs($seller)
            ->getJson(route('seller.orders.modal', $offer))
            ->assertOk()
            ->assertJsonPath('order.offer_id', $offer->id)
            ->assertJsonPath('order.reference_no', $rfq->reference_no)
            ->assertJsonPath('order.order_url', route('seller.orders.show', $offer))
            ->assertJsonPath('order.create_invoice_url', route('seller.orders.invoices.store', $offer));
    }

    public function test_seller_orders_summary_enables_invoice_action_after_order_information_is_complete(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = $this->createReadySeller('Atlas Marine', 'TR', 'Istanbul', 'TRIST');

        [$rfq, $offer] = $this->createConfirmedServiceOrder($buyer, $seller, 'RFQ-SELLER-ORDER-INVOICE-001');

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

        $this->actingAs($seller)
            ->get(route('seller.orders'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Supplier/Dashboard/Orders')
                ->where('orders.0.offer_id', $offer->id)
                ->where('orders.0.reference_no', $rfq->reference_no)
                ->where('orders.0.order_workflow_status', Offer::ORDER_STATUS_INVOICE_PENDING)
                ->where('orders.0.order_workflow_status_label', 'Invoice Pending')
                ->where('orders.0.can_manage_invoices', true)
            );
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
            'general_notes' => 'Seller order page test',
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
            'award_scope_policy' => Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED,
            'payment_order_confirmation' => 30,
            'payment_before_shipment' => 70,
            'submitted_at' => now(),
        ]);

        $rfq->attachments()->create([
            'disk' => 'public',
            'path' => 'rfqs/seller-orders/service-request.pdf',
            'original_name' => 'seller-service-request.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1400,
        ]);

        $offer->attachments()->create([
            'disk' => 'public',
            'path' => 'offers/seller-orders/service-offer.pdf',
            'original_name' => 'seller-service-offer.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1600,
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

    private function createReadySeller(string $companyName, string $countryCode, string $city, string $unlocode): User
    {
        $port = Port::query()->create([
            'unlocode' => $unlocode,
            'country_code' => $countryCode,
            'location_code' => substr($unlocode, -3),
            'country_name' => $city === 'Dubai' ? 'United Arab Emirates' : 'Turkey',
            'port_name' => $city,
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => $companyName,
            'country' => $port->country_name,
            'email_verified_at' => now(),
            'approval_status' => 'approved',
            'approved_at' => now(),
            'phone' => '+90 5551234567',
            'contact_email' => Str::slug($companyName).'-seller@example.test',
            'company_address_line' => "{$city} Harbor Center",
            'company_city' => $city,
            'company_overview' => str_repeat("{$companyName} provides certified marine supply and service support across key commercial ports. ", 4),
            'company_logo_path' => 'offers/1/items/1/SRDOM41GUldPys4w8HppPoUCBlQx1IWaUM5vo8DA.png',
            'service_category_ids' => [1],
            'service_country_codes' => [$countryCode],
            'registration_number' => "{$countryCode}-READY-".strtoupper(substr(md5($companyName), 0, 6)),
            'company_registration_documents' => [['path' => 'rfqs/10/items/21/fJ1lllxW2nFcmHe9CNEwUXsT7fcs5XmaqNJ2aztC.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        return $seller;
    }
}
