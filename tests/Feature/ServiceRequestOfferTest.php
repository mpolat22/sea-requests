<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\RfqSupplierRecipient;
use App\Models\SupplierServiceListing;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ServiceRequestOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_submit_service_request_offer_and_persist_service_fields(): void
    {
        Notification::fake();

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $seller = $this->createReadySeller([
            'country_name' => 'Sri Lanka',
            'country_code' => 'LK',
            'port_name' => 'Colombo',
            'unlocode' => 'LKCMB',
        ]);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SERVICE-TEST-001',
            'company_name' => 'Test Buyer Co',
            'ship_name' => 'MV Test',
            'request_type' => 'service_request',
            'country_name' => 'Sri Lanka',
            'port_name' => 'Colombo',
            'country_names' => ['Sri Lanka'],
            'ports_by_country' => [
                'Sri Lanka' => [
                    ['id' => 1, 'name' => 'Colombo', 'unlocode' => 'LKCMB'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Service request note',
            'service_title' => 'Fresh Water Supply',
            'service_description' => 'Prompt delivery of fresh water to anchorage.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        RfqSupplierRecipient::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'company_name' => $seller->company_name,
            'country_name' => 'Sri Lanka',
            'port_name' => 'Colombo',
        ]);

        $response = $this
            ->actingAs($seller)
            ->post(route('seller.offers.store', $rfq), [
                'intent' => 'submit',
                'service_total_price' => '1500',
                'completion_time' => 'Within 12 hours after confirmation',
                'offer_validity' => '7 days',
                'including_tax' => true,
                'tax_amount' => '',
                'including_mobilization' => false,
                'mobilization_cost' => '125',
                'including_packing' => true,
                'packing_cost' => '',
                'including_freight' => true,
                'freight_cost' => '',
                'delivery_terms' => 'DAP',
                'other_delivery_terms' => 'Attendance at Colombo anchorage',
                'payment_order_confirmation' => '30',
                'payment_before_shipment' => '70',
                'payment_invoice_days' => '0',
                'other_payment_terms' => 'Balance before attendance',
                'service_clarification' => 'Certified potable water and meter log included.',
                'general_note' => 'Please confirm berth window in advance.',
            ]);

        $response->assertRedirect(route('seller.requests'));
        $response->assertSessionHas('success');

        $offer = Offer::query()
            ->where('rfq_id', $rfq->id)
            ->where('seller_id', $seller->id)
            ->first();

        $this->assertNotNull($offer);
        $this->assertSame(Offer::STATUS_SUBMITTED, $offer->status);
        $this->assertSame(Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED, $offer->awardScopePolicy());
        $this->assertSame('1500.00', $offer->total_offer_amount);
        $this->assertTrue($offer->including_tax);
        $this->assertFalse($offer->including_mobilization);
        $this->assertSame('125.00', $offer->mobilization_cost);
        $this->assertSame('1625.00', $offer->grand_total);
        $this->assertSame('Within 12 hours after confirmation', $offer->completion_time);
        $this->assertSame('7 days', $offer->offer_validity);
        $this->assertSame('Certified potable water and meter log included.', $offer->service_clarification);
        $this->assertSame('Please confirm berth window in advance.', $offer->general_note);
        $this->assertCount(0, $offer->items);

        Notification::assertSentTo($buyer, MarketplaceNotification::class);
        Notification::assertSentTo($seller, MarketplaceNotification::class);
    }

    public function test_service_request_submit_requires_total_price_and_mobilization_cost_when_not_included(): void
    {
        Notification::fake();

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $seller = $this->createReadySeller([
            'country_name' => 'Panama',
            'country_code' => 'PA',
            'port_name' => 'Balboa',
            'unlocode' => 'PABLB',
        ]);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SERVICE-TEST-002',
            'company_name' => 'Test Buyer Co',
            'ship_name' => 'MV Test',
            'request_type' => 'service_request',
            'country_name' => 'Panama',
            'port_name' => 'Balboa',
            'country_names' => ['Panama'],
            'ports_by_country' => [
                'Panama' => [
                    ['id' => 2, 'name' => 'Balboa', 'unlocode' => 'PABLB'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Gas-free certification required.',
            'service_title' => 'Marine Chemist Attendance',
            'service_description' => 'Gas testing and atmosphere monitoring.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        RfqSupplierRecipient::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'company_name' => $seller->company_name,
            'country_name' => 'Panama',
            'port_name' => 'Balboa',
        ]);

        $response = $this
            ->actingAs($seller)
            ->from(route('seller.offers.create', $rfq))
            ->post(route('seller.offers.store', $rfq), [
                'intent' => 'submit',
                'service_total_price' => '',
                'completion_time' => 'Same day attendance',
                'including_tax' => true,
                'tax_amount' => '',
                'including_mobilization' => false,
                'mobilization_cost' => '',
                'including_packing' => true,
                'packing_cost' => '',
                'including_freight' => true,
                'freight_cost' => '',
            ]);

        $response->assertRedirect(route('seller.offers.create', $rfq));
        $response->assertSessionHasErrors([
            'service_total_price',
            'mobilization_cost',
        ]);

        $this->assertDatabaseMissing('offers', [
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'status' => Offer::STATUS_SUBMITTED,
        ]);

        Notification::assertNothingSent();
    }

    public function test_later_matching_seller_can_see_public_service_request_in_dashboard_and_submit_offer(): void
    {
        Notification::fake();

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();
        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SERVICE-PUBLIC-003',
            'company_name' => 'Discovery Buyer Co',
            'ship_name' => 'MV Discovery',
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
            'general_notes' => 'Open public service request without pre-routed suppliers.',
            'service_title' => 'Deck Cleaning Attendance',
            'service_description' => 'Deck cleaning team attendance required at short notice.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        [$seller, $listing] = $this->createSupplierListingForPort($port);

        $this->actingAs($seller)
            ->get(route('seller.requests'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Supplier/Dashboard/IncomingRequests')
                ->has('incomingRequests', 1)
                ->where('incomingRequests.0.rfq_id', $rfq->id)
                ->where('incomingRequests.0.reference_no', $rfq->reference_no)
                ->where('incomingRequests.0.show_url', route('seller.rfqs.show', $rfq))
                ->where('incomingRequests.0.offer_url', route('seller.offers.create', $rfq))
            );

        $this->actingAs($seller)
            ->get(route('seller.offers.create', $rfq))
            ->assertOk();

        $response = $this
            ->actingAs($seller)
            ->post(route('seller.offers.store', $rfq), [
                'intent' => 'submit',
                'service_total_price' => '1800',
                'completion_time' => 'Within 18 hours after confirmation',
                'offer_validity' => '5 days',
                'including_tax' => true,
                'tax_amount' => '',
                'including_mobilization' => true,
                'mobilization_cost' => '',
                'including_packing' => true,
                'packing_cost' => '',
                'including_freight' => true,
                'freight_cost' => '',
                'delivery_terms' => 'DAP',
                'other_delivery_terms' => 'Attendance at nominated berth',
                'payment_order_confirmation' => '50',
                'payment_before_shipment' => '50',
                'payment_invoice_days' => '0',
                'other_payment_terms' => 'Balance before boarding',
                'service_clarification' => 'Crew and equipment can attend on short notice.',
                'general_note' => 'Please confirm access pass requirements.',
            ]);

        $response->assertRedirect(route('seller.requests'));
        $response->assertSessionHas('success');

        $offer = Offer::query()
            ->where('rfq_id', $rfq->id)
            ->where('seller_id', $seller->id)
            ->first();

        $this->assertNotNull($offer);
        $this->assertSame(Offer::STATUS_SUBMITTED, $offer->status);

        $this->assertDatabaseHas('rfq_supplier_recipients', [
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'supplier_service_listing_id' => $listing->id,
            'company_name' => $seller->company_name,
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
        ]);

        Notification::assertSentTo($buyer, MarketplaceNotification::class);
        Notification::assertSentTo($seller, MarketplaceNotification::class);
    }

    public function test_seller_offer_create_page_uses_supplier_rfq_back_url_and_closed_fallback_returns_to_workspace(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $seller = $this->createReadySeller([
            'country_name' => 'Sri Lanka',
            'country_code' => 'LK',
            'port_name' => 'Colombo',
            'unlocode' => 'LKCMB',
        ]);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SERVICE-TEST-004',
            'company_name' => 'Workspace Buyer Co',
            'ship_name' => 'MV Workspace',
            'request_type' => 'service_request',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => 'Sri Lanka',
            'port_name' => 'Colombo',
            'country_names' => ['Sri Lanka'],
            'ports_by_country' => [
                'Sri Lanka' => [
                    ['id' => 1, 'name' => 'Colombo', 'unlocode' => 'LKCMB'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Seller offer back link test.',
            'service_title' => 'Tank Cleaning',
            'service_description' => 'Workspace back link test.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        RfqSupplierRecipient::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'company_name' => $seller->company_name,
            'country_name' => 'Sri Lanka',
            'port_name' => 'Colombo',
        ]);

        $this->actingAs($seller)
            ->get(route('seller.offers.create', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Supplier/OfferCreate')
                ->where('backUrl', route('seller.rfqs.show', $rfq))
            );

        $rfq->forceFill(['status' => Rfq::STATUS_CLOSED])->save();

        $this->actingAs($seller)
            ->get(route('seller.offers.create', $rfq))
            ->assertRedirect(route('seller.rfqs.show', $rfq));
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

    private function createSupplierListingForPort(Port $port): array
    {
        $seller = $this->createReadySeller([
            'company_name' => 'Atlas Marine Service',
            'country_name' => $port->country_name,
            'country_code' => $port->country_code,
            'port_name' => $port->port_name,
            'unlocode' => $port->unlocode,
        ]);

        $listing = SupplierServiceListing::query()->create([
            'seller_id' => $seller->id,
            'listing_key' => 'atlas-service-'.Str::uuid(),
            'company_name' => $seller->company_name,
            'contact_name' => $seller->name,
            'country' => $port->country_name,
            'summary' => 'Rapid onboard service attendance.',
            'vendor_slug' => Str::slug($seller->company_name),
            'search_text' => "{$seller->company_name} {$port->country_name} {$port->port_name}",
            'is_visible' => true,
        ]);

        $listing->ports()->create([
            'country_code' => $port->country_code,
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
            'unlocode' => $port->unlocode,
        ]);

        return [$seller, $listing];
    }

    private function createReadySeller(array $portData): User
    {
        $port = Port::query()->firstOrCreate(
            ['unlocode' => $portData['unlocode']],
            [
                'country_code' => $portData['country_code'],
                'location_code' => substr($portData['unlocode'], -3),
                'country_name' => $portData['country_name'],
                'port_name' => $portData['port_name'],
                'is_active' => true,
            ]
        );

        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => $portData['company_name'] ?? 'Service Ready Supplier',
            'country' => $portData['country_name'],
            'email_verified_at' => now(),
            'approval_status' => 'approved',
            'approved_at' => now(),
            'phone' => '+94 771234567',
            'contact_email' => 'service-ready@example.test',
            'company_address_line' => "{$portData['port_name']} Harbor Office",
            'company_city' => $portData['port_name'],
            'company_overview' => str_repeat('Approved service supplier with documented marine attendance capability, verified coverage, and complete legal documents. ', 4),
            'company_logo_path' => 'offers/1/items/1/SRDOM41GUldPys4w8HppPoUCBlQx1IWaUM5vo8DA.png',
            'service_category_ids' => [1],
            'service_country_codes' => [$portData['country_code']],
            'registration_number' => "{$portData['country_code']}-SERVICE-READY",
            'company_registration_documents' => [['path' => 'rfqs/10/items/21/fJ1lllxW2nFcmHe9CNEwUXsT7fcs5XmaqNJ2aztC.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        return $seller;
    }
}
