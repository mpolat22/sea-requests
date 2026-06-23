<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SellerRfqWorkspacePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_dashboard_request_detail_uses_supplier_workspace_mode(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $port = $this->createActivePort();
        $seller = $this->createReadySeller($port);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SUPPLIER-WORKSPACE-001',
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Workspace',
            'imo_number' => '1234567',
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
            'due_date' => now()->addDays(4)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Supplier workspace page test.',
            'service_title' => 'Hull Cleaning Attendance',
            'service_description' => 'Supplier workspace detail test.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'service_request',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'grand_total' => 2400,
            'submitted_at' => now(),
        ]);

        $this->actingAs($seller)
            ->get(route('seller.rfqs.show', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Request/RequestShow')
                ->where('backUrl', route('seller.requests'))
                ->where('rfq.page_mode', 'seller_workspace')
                ->where('rfq.show_supplier_workspace', true)
                ->where('rfq.eyebrow', 'Supplier RFQ')
            );
    }

    public function test_public_request_page_for_seller_stays_in_public_mode_and_points_to_supplier_workspace(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $port = $this->createActivePort();
        $seller = $this->createReadySeller($port);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SUPPLIER-WORKSPACE-002',
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Public',
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
            'due_date' => now()->addDays(4)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Supplier public request bridge page test.',
            'service_title' => 'Pump Service',
            'service_description' => 'Public bridge page test.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'service_request',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'grand_total' => 1800,
            'submitted_at' => now(),
        ]);

        $this->actingAs($seller)
            ->get(route('rfqs.show', ['rfq' => $rfq, 'slug' => $rfq->publicSlug()]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Request/RequestShow')
                ->where('backUrl', route('requests.index'))
                ->where('rfq.page_mode', 'public')
                ->where('rfq.show_supplier_workspace', false)
                ->where('rfq.supplier_rfq_url', route('seller.rfqs.show', $rfq))
                ->where('rfq.eyebrow', 'Published Request')
            );
    }

    public function test_closed_submitted_seller_workspace_exposes_null_offer_url_without_breaking_workspace_context(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $port = $this->createActivePort();
        $seller = $this->createReadySeller($port);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SUPPLIER-WORKSPACE-003',
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Closed',
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
            'due_date' => now()->addDays(4)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_CLOSED,
            'general_notes' => 'Closed submitted seller workspace page test.',
            'service_title' => 'Boiler Attendance',
            'service_description' => 'Closed submitted workspace test.',
            'items_count' => 1,
            'submitted_at' => now()->subDay(),
        ]);

        Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'service_request',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'grand_total' => 2100,
            'submitted_at' => now()->subDay(),
        ]);

        $this->actingAs($seller)
            ->get(route('seller.rfqs.show', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Request/RequestShow')
                ->where('rfq.page_mode', 'seller_workspace')
                ->where('rfq.offer_state', 'closed')
                ->where('rfq.offer_url', null)
                ->where('rfq.my_offer.status', Offer::STATUS_SUBMITTED)
            );
    }

    private function createActivePort(): Port
    {
        return Port::query()->create([
            'name' => 'Durres',
            'port_name' => 'Durres',
            'country_name' => 'Albania',
            'country_code' => 'AL',
            'unlocode' => 'ALDRZ',
            'is_active' => true,
            'latitude' => 41.3231,
            'longitude' => 19.4540,
        ]);
    }

    private function createReadySeller(Port $port): User
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'country' => $port->country_name,
            'email_verified_at' => now(),
            'approval_status' => 'approved',
            'approved_at' => now(),
            'phone' => '+355 681234567',
            'contact_email' => 'workspace-seller@example.test',
            'company_address_line' => 'Dock Office 14',
            'company_city' => $port->port_name,
            'company_overview' => str_repeat('Supplier workspace ready profile with verified marine support coverage and complete compliance documentation. ', 4),
            'company_logo_path' => 'offers/1/items/1/SRDOM41GUldPys4w8HppPoUCBlQx1IWaUM5vo8DA.png',
            'service_category_ids' => [1],
            'service_country_codes' => [$port->country_code],
            'registration_number' => "{$port->country_code}-WORKSPACE-001",
            'company_registration_documents' => [['path' => 'rfqs/10/items/21/fJ1lllxW2nFcmHe9CNEwUXsT7fcs5XmaqNJ2aztC.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        return $seller;
    }
}
