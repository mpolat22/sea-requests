<?php

namespace Tests\Feature;

use App\Models\Rfq;
use App\Models\RfqSupplierRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateRequestVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_request_is_visible_in_requests_index_for_buyer_owner_and_selected_supplier(): void
    {
        [$rfq, $buyer, $selectedSupplier] = $this->createPrivateRfq();

        $this->actingAs($buyer)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee($rfq->reference_no);

        $this->actingAs($selectedSupplier)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee($rfq->reference_no);
    }

    public function test_private_request_is_visible_in_requests_index_for_admin(): void
    {
        [$rfq] = $this->createPrivateRfq();

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee($rfq->reference_no);
    }

    public function test_private_request_is_hidden_from_unrelated_requests_directory_visitors(): void
    {
        [$rfq] = $this->createPrivateRfq();

        $otherSeller = User::factory()->create([
            'role' => 'seller',
        ]);

        $otherBuyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $this->get(route('requests.index'))
            ->assertOk()
            ->assertDontSee($rfq->reference_no);

        $this->actingAs($otherSeller)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertDontSee($rfq->reference_no);

        $this->actingAs($otherBuyer)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertDontSee($rfq->reference_no);
    }

    public function test_private_request_show_respects_buyer_supplier_and_admin_access(): void
    {
        [$rfq, $buyer, $selectedSupplier] = $this->createPrivateRfq();

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $otherSeller = User::factory()->create([
            'role' => 'seller',
        ]);

        $showUrl = route('rfqs.show', [
            'rfq' => $rfq,
            'slug' => $rfq->publicSlug(),
        ]);

        $this->get($showUrl)->assertNotFound();

        $this->actingAs($buyer)
            ->get($showUrl)
            ->assertOk();

        $this->actingAs($selectedSupplier)
            ->get($showUrl)
            ->assertOk();

        $this->actingAs($admin)
            ->get($showUrl)
            ->assertOk();

        $this->actingAs($otherSeller)
            ->get($showUrl)
            ->assertNotFound();
    }

    private function createPrivateRfq(): array
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $selectedSupplier = User::factory()->create([
            'role' => 'seller',
        ]);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-PRIVATE-001',
            'company_name' => 'Private Buyer Co',
            'ship_name' => 'MV Private',
            'request_type' => 'service_request',
            'visibility_scope' => Rfq::VISIBILITY_PRIVATE_SUPPLIER,
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
            'general_notes' => 'Private request note',
            'service_title' => 'Private Service Attendance',
            'service_description' => 'Private supplier-only service request.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        RfqSupplierRecipient::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $selectedSupplier->id,
            'company_name' => $selectedSupplier->company_name,
            'country_name' => 'Albania',
            'port_name' => 'Durres',
        ]);

        return [$rfq, $buyer, $selectedSupplier];
    }
}
