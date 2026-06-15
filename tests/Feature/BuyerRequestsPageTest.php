<?php

namespace Tests\Feature;

use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerRequestsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_requests_page_shows_only_owned_rfqs_including_private_requests(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $otherBuyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $ownedPublic = $this->createRfq($buyer, 'RFQ-BUYER-PUBLIC-001', Rfq::VISIBILITY_PUBLIC_MARKETPLACE);
        $ownedPrivate = $this->createRfq($buyer, 'RFQ-BUYER-PRIVATE-001', Rfq::VISIBILITY_PRIVATE_SUPPLIER);
        $foreignRfq = $this->createRfq($otherBuyer, 'RFQ-OTHER-001', Rfq::VISIBILITY_PUBLIC_MARKETPLACE);

        $this->actingAs($buyer)
            ->get(route('buyer.requests'))
            ->assertOk()
            ->assertSee($ownedPublic->reference_no)
            ->assertSee($ownedPrivate->reference_no)
            ->assertDontSee($foreignRfq->reference_no);
    }

    public function test_non_buyers_cannot_access_buyer_requests_page(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($seller)
            ->get(route('buyer.requests'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('buyer.requests'))
            ->assertForbidden();
    }

    public function test_buyer_requests_page_filters_results_server_side(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $matchingRfq = $this->createRfq(
            buyer: $buyer,
            referenceNo: 'RFQ-SERVICE-ALPHA',
            visibilityScope: Rfq::VISIBILITY_PRIVATE_SUPPLIER,
            serviceTitle: 'Alpha Engine Attendance'
        );

        $nonMatchingRfq = $this->createRfq(
            buyer: $buyer,
            referenceNo: 'RFQ-SPARE-BETA',
            visibilityScope: Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            serviceTitle: 'Beta Pump Request'
        );

        $this->actingAs($buyer)
            ->get(route('buyer.requests', ['search' => 'alpha']))
            ->assertOk()
            ->assertSee($matchingRfq->reference_no)
            ->assertDontSee($nonMatchingRfq->reference_no);

        $this->actingAs($buyer)
            ->get(route('buyer.requests', ['search' => 'private']))
            ->assertOk()
            ->assertSee($matchingRfq->reference_no)
            ->assertDontSee($nonMatchingRfq->reference_no);
    }

    private function createRfq(User $buyer, string $referenceNo, string $visibilityScope, string $serviceTitle = 'Buyer Request'): Rfq
    {
        return Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => $referenceNo,
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Buyer',
            'request_type' => 'service_request',
            'visibility_scope' => $visibilityScope,
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
            'status' => Rfq::STATUS_SUBMITTED,
            'general_notes' => 'Buyer request page test',
            'service_title' => $serviceTitle,
            'service_description' => str_repeat('Private buyer request details. ', 10),
            'items_count' => 1,
            'submitted_at' => now(),
        ]);
    }
}
