<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\RfqSupplierRecipient;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BuyerRfqEditPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_rfq_without_offers_can_still_be_fully_edited(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $originalPort = $this->createActivePort();
        $updatedPort = $this->createActivePort(
            countryName: 'Greece',
            countryCode: 'GR',
            portName: 'Piraeus',
            locationCode: 'PIR',
            unlocode: 'GRPIR',
        );

        $rfq = $this->createServiceRfq($buyer, $originalPort, [
            'reference_no' => 'RFQ-EDIT-FULL-001',
            'status' => Rfq::STATUS_SUBMITTED,
            'service_title' => 'Original full edit title',
            'service_description' => $this->longDescription('Original full edit service description'),
        ]);

        $this->actingAs($buyer)
            ->get(route('rfqs.edit', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Create/Page')
                ->where('mode', 'edit')
                ->where('editPolicy.can_edit', true)
                ->where('editPolicy.general_only', false)
            );

        $response = $this->actingAs($buyer)
            ->put(route('rfqs.update', $rfq), $this->serviceUpdatePayload($rfq, $updatedPort, [
                'reference_no' => 'RFQ-EDIT-FULL-UPDATED',
                'general_notes' => 'Updated fully editable notes',
                'service_title' => 'Updated full edit title',
                'service_description' => $this->longDescription('Updated full edit service description'),
            ]));

        $response->assertRedirect(route('buyer.requests'));
        $response->assertSessionHas('success', 'rfq-updated');

        $rfq->refresh();

        $this->assertSame('RFQ-EDIT-FULL-UPDATED', $rfq->reference_no);
        $this->assertSame('Updated full edit title', $rfq->service_title);
        $this->assertSame(rtrim($this->longDescription('Updated full edit service description')), $rfq->service_description);
        $this->assertSame('Updated fully editable notes', $rfq->general_notes);
        $this->assertSame(['Greece'], $rfq->country_names);
    }

    public function test_submitted_rfq_with_offers_is_general_only_and_keeps_locked_fields_unchanged(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create(['role' => 'seller']);
        $originalPort = $this->createActivePort();
        $updatedPort = $this->createActivePort(
            countryName: 'Greece',
            countryCode: 'GR',
            portName: 'Piraeus',
            locationCode: 'PIR',
            unlocode: 'GRPIR',
        );
        $category = $this->createCategory('Hydraulic Systems');
        $subcategory = $this->createSubcategory($category, 'Hydraulic Pump Service');
        $brand = $this->createBrand('MAN');

        $rfq = $this->createServiceRfq($buyer, $originalPort, [
            'reference_no' => 'RFQ-EDIT-GENERAL-001',
            'status' => Rfq::STATUS_SUBMITTED,
            'category_ids' => [$category->id],
            'subcategory_ids' => [$subcategory->id],
            'brand_ids' => [$brand->id],
            'service_title' => 'Original locked title',
            'service_description' => $this->longDescription('Original locked description'),
        ]);

        Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'service_request',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'including_tax' => true,
            'including_packing' => true,
            'including_freight' => true,
            'including_mobilization' => true,
            'total_offer_amount' => 1000,
            'grand_total' => 1000,
            'submitted_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('rfqs.edit', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Create/Page')
                ->where('mode', 'edit')
                ->where('editPolicy.can_edit', true)
                ->where('editPolicy.general_only', true)
                ->where('editPolicy.reason', 'offers_received')
            );

        $response = $this->actingAs($buyer)
            ->put(route('rfqs.update', $rfq), $this->serviceUpdatePayload($rfq, $updatedPort, [
                'reference_no' => 'RFQ-EDIT-GENERAL-UPDATED',
                'general_notes' => 'General-only fields updated',
                'service_title' => 'Attempted changed title',
                'service_description' => $this->longDescription('Attempted changed description'),
                'category_ids' => [],
                'subcategory_ids' => [],
                'brand_ids' => [],
                'supplier_recipient_ids' => [],
            ]));

        $response->assertRedirect(route('buyer.requests'));
        $response->assertSessionHas('success', 'rfq-updated');

        $rfq->refresh();

        $this->assertSame('RFQ-EDIT-GENERAL-UPDATED', $rfq->reference_no);
        $this->assertSame('General-only fields updated', $rfq->general_notes);
        $this->assertSame(['Greece'], $rfq->country_names);
        $this->assertSame('Original locked title', $rfq->service_title);
        $this->assertSame($this->longDescription('Original locked description'), $rfq->service_description);
        $this->assertSame([$category->id], $rfq->category_ids);
        $this->assertSame([$subcategory->id], $rfq->subcategory_ids);
        $this->assertSame([$brand->id], $rfq->brand_ids);
    }

    public function test_overdue_submitted_rfq_is_general_only_editable(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $port = $this->createActivePort();

        $rfq = $this->createServiceRfq($buyer, $port, [
            'reference_no' => 'RFQ-EDIT-OVERDUE-001',
            'status' => Rfq::STATUS_SUBMITTED,
            'due_date' => now()->subDay()->toDateString(),
            'submitted_at' => now()->subDays(2),
        ]);

        $this->actingAs($buyer)
            ->get(route('rfqs.edit', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Create/Page')
                ->where('editPolicy.can_edit', true)
                ->where('editPolicy.general_only', true)
                ->where('editPolicy.reason', 'overdue_extendable')
            );
    }

    public function test_awarded_rfq_cannot_be_edited_anymore(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create(['role' => 'seller']);
        $port = $this->createActivePort();

        $rfq = $this->createServiceRfq($buyer, $port, [
            'reference_no' => 'RFQ-EDIT-LOCKED-001',
            'status' => Rfq::STATUS_SUBMITTED,
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
            'total_offer_amount' => 1200,
            'grand_total' => 1200,
            'submitted_at' => now(),
        ]);

        OfferAward::query()->create([
            'rfq_id' => $rfq->id,
            'buyer_id' => $buyer->id,
            'offer_id' => $offer->id,
            'request_type' => 'service_request',
            'status' => OfferAward::STATUS_CONFIRMED,
            'buyer_note' => 'Award locked.',
            'confirmed_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('rfqs.edit', $rfq))
            ->assertRedirect($rfq->buyerShowUrl())
            ->assertSessionHas('error', 'rfq-edit-locked');

        $this->actingAs($buyer)
            ->put(route('rfqs.update', $rfq), $this->serviceUpdatePayload($rfq, $port, [
                'general_notes' => 'Should not save',
            ]))
            ->assertRedirect($rfq->buyerShowUrl())
            ->assertSessionHas('error', 'rfq-edit-locked');
    }

    public function test_private_service_request_edit_keeps_supplier_target_context_and_cannot_drop_supplier_scope(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'Atlas Marine Service',
            'country' => 'Albania',
            'service_country_codes' => ['AL'],
            'service_brand_ids' => [],
        ]);

        $port = $this->createActivePort();
        $category = $this->createCategory('Hydraulic Systems');
        $subcategory = $this->createSubcategory($category, 'Hydraulic Pump Service');

        $seller->servicePorts()->attach($port->id);

        $listing = SupplierServiceListing::query()->create([
            'seller_id' => $seller->id,
            'listing_key' => 'atlas-private-'.Str::uuid(),
            'company_name' => $seller->company_name,
            'contact_name' => $seller->name,
            'country' => $port->country_name,
            'summary' => 'Private targeted service listing.',
            'category_id' => $category->id,
            'category_name' => $category->name,
            'category_slug' => $category->slug,
            'subcategory_id' => $subcategory->id,
            'subcategory_name' => $subcategory->name,
            'subcategory_slug' => $subcategory->slug,
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

        $rfq = $this->createServiceRfq($buyer, $port, [
            'reference_no' => 'RFQ-EDIT-PRIVATE-001',
            'status' => Rfq::STATUS_SUBMITTED,
            'visibility_scope' => Rfq::VISIBILITY_PRIVATE_SUPPLIER,
            'category_ids' => [$category->id],
            'subcategory_ids' => [$subcategory->id],
        ]);

        RfqSupplierRecipient::query()->create([
            'rfq_id' => $rfq->id,
            'supplier_service_listing_id' => $listing->id,
            'seller_id' => $seller->id,
            'company_name' => $seller->company_name,
            'category_name' => $category->name,
            'subcategory_name' => $subcategory->name,
            'country_name' => $port->country_name,
            'port_name' => $port->port_name,
        ]);

        $this->actingAs($buyer)
            ->get(route('rfqs.edit', $rfq))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Create/Page')
                ->where('editPolicy.can_edit', true)
                ->where('editPolicy.general_only', false)
                ->where('supplierTarget.company_name', 'Atlas Marine Service')
                ->where('supplierTarget.source', 'supplier_detail')
                ->where('supplierTarget.request_type_locked', true)
                ->where('supplierTarget.candidate_listing_ids.0', $listing->id)
            );

        $response = $this->actingAs($buyer)
            ->from(route('rfqs.edit', $rfq))
            ->put(route('rfqs.update', $rfq), $this->serviceUpdatePayload($rfq, $port, [
                'supplier_recipient_ids' => [],
                'general_notes' => 'Private request general info updated',
            ]));

        $response->assertRedirect(route('buyer.requests'));
        $response->assertSessionHas('success', 'rfq-updated');

        $rfq->refresh();

        $this->assertSame(Rfq::VISIBILITY_PRIVATE_SUPPLIER, $rfq->visibilityScope());
        $this->assertSame('Private request general info updated', $rfq->general_notes);
        $this->assertDatabaseHas('rfq_supplier_recipients', [
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'supplier_service_listing_id' => $listing->id,
        ]);
    }

    private function serviceUpdatePayload(Rfq $rfq, Port $port, array $overrides = []): array
    {
        return array_replace_recursive([
            'request_type' => 'service_request',
            'reference_no' => $rfq->reference_no,
            'company_name' => $rfq->company_name,
            'ship_name' => $rfq->ship_name,
            'country_names' => [$port->country_name],
            'ports_by_country' => [
                $port->country_name => [$port->id],
            ],
            'category_ids' => $rfq->category_ids ?? [],
            'subcategory_ids' => $rfq->subcategory_ids ?? [],
            'brand_ids' => $rfq->brand_ids ?? [],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => 'open',
            'general_notes' => 'Updated notes',
            'service_title' => $rfq->service_title,
            'service_description' => $rfq->service_description,
            'service_files' => [],
            'existing_service_attachment_ids' => [],
            'supplier_recipient_ids' => $rfq->supplierRecipients()->pluck('supplier_service_listing_id')->filter()->values()->all(),
            'items' => [
                [
                    'product_name' => '',
                    'part_no' => '',
                    'quantity' => '',
                    'unit' => '',
                    'manufacturer' => '',
                    'model_type' => '',
                    'serial_number' => '',
                    'catalog_code' => '',
                    'rob' => '',
                    'drawing_number' => '',
                    'quality' => '',
                    'comments' => '',
                ],
            ],
        ], $overrides);
    }

    private function createServiceRfq(User $buyer, Port $port, array $overrides = []): Rfq
    {
        $attributes = array_merge([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-EDIT-BASE-001',
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Buyer',
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
            'category_ids' => [],
            'subcategory_ids' => [],
            'brand_ids' => [],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_DRAFT,
            'general_notes' => 'Draft service request',
            'service_title' => 'Service scope',
            'service_description' => $this->longDescription('Detailed service scope'),
            'items_count' => 1,
            'submitted_at' => null,
        ], $overrides);

        if (($attributes['status'] ?? null) === Rfq::STATUS_SUBMITTED && empty($attributes['submitted_at'])) {
            $attributes['submitted_at'] = now();
        }

        return Rfq::query()->create($attributes);
    }

    private function createActivePort(
        string $countryName = 'Albania',
        string $countryCode = 'AL',
        string $portName = 'Durres',
        string $locationCode = 'DRZ',
        string $unlocode = 'ALDRZ',
    ): Port {
        return Port::query()->create([
            'unlocode' => $unlocode,
            'country_code' => $countryCode,
            'location_code' => $locationCode,
            'country_name' => $countryName,
            'port_name' => $portName,
            'is_active' => true,
        ]);
    }

    private function createCategory(string $name): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'has_subcategories' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createSubcategory(Category $category, string $name): Subcategory
    {
        return Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createBrand(string $name): Brand
    {
        return Brand::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function longDescription(string $prefix): string
    {
        return trim($prefix.'. '.str_repeat('Extended service scope with operational notes, attendance requirements, and delivery expectations. ', 3));
    }
}
