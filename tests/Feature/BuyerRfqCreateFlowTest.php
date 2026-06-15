<?php

namespace Tests\Feature;

use App\Jobs\DispatchRfqDeliveryJob;
use App\Models\Category;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BuyerRfqCreateFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_open_standard_rfq_create_page(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();

        $this->actingAs($buyer)
            ->get(route('rfqs.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Create/Page')
                ->where('mode', 'create')
                ->where('submitMethod', 'post')
                ->where('actionUrl', route('rfqs.store'))
                ->where('defaults.request_type', 'spare_parts')
                ->where('defaults.status', 'open')
                ->where('supplierTarget', null)
                ->where('countryOptions.0', $port->country_name)
            );
    }

    public function test_guests_are_redirected_and_non_buyers_are_forbidden_from_create_flow(): void
    {
        $port = $this->createActivePort();
        $payload = $this->sparePartsPayload($port);

        $this->get(route('rfqs.create'))
            ->assertRedirect(route('login'));

        $this->post(route('rfqs.store'), $payload)
            ->assertRedirect(route('login'));

        foreach (['seller', 'admin'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
            ]);

            $this->actingAs($user)
                ->get(route('rfqs.create'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('rfqs.store'), $payload)
                ->assertForbidden();
        }
    }

    public function test_buyer_can_create_spare_parts_draft_with_item_attachment(): void
    {
        Storage::fake('public');
        Queue::fake();
        Notification::fake();

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();
        $itemFile = UploadedFile::fake()->create('liner-spec.pdf', 24, 'application/pdf');

        $response = $this->actingAs($buyer)
            ->post(route('rfqs.store'), $this->sparePartsPayload($port, [
                'reference_no' => 'RFQ-SPARE-CREATE-001',
                'status' => 'draft',
                'items' => [[
                    'product_name' => 'Cylinder liner',
                    'part_no' => 'CL-100',
                    'quantity' => '2',
                    'unit' => 'PCS',
                    'manufacturer' => 'MAN',
                    'model_type' => 'B&W',
                    'serial_number' => 'SER-001',
                    'catalog_code' => 'CAT-100',
                    'rob' => '1',
                    'drawing_number' => 'DWG-100',
                    'quality' => 'genuine',
                    'comments' => 'Need quotation with lead time.',
                    'files' => [$itemFile],
                ]],
            ]));

        $response->assertRedirect(route('buyer.requests'));
        $response->assertSessionHas('success', 'rfq-created');

        $rfq = Rfq::query()
            ->where('reference_no', 'RFQ-SPARE-CREATE-001')
            ->with(['items.attachments', 'attachments', 'supplierRecipients'])
            ->firstOrFail();

        $this->assertSame(Rfq::STATUS_DRAFT, $rfq->status);
        $this->assertSame(Rfq::VISIBILITY_PUBLIC_MARKETPLACE, $rfq->visibilityScope());
        $this->assertSame('spare_parts', $rfq->request_type);
        $this->assertNull($rfq->submitted_at);
        $this->assertCount(1, $rfq->items);
        $this->assertCount(0, $rfq->attachments);
        $this->assertCount(0, $rfq->supplierRecipients);

        $item = $rfq->items->first();

        $this->assertNotNull($item);
        $this->assertSame('Cylinder liner', $item->product_name);
        $this->assertSame('PCS', $item->unit);
        $this->assertCount(1, $item->attachments);
        Storage::disk('public')->assertExists($item->attachments->first()->path);

        Queue::assertNothingPushed();
    }

    public function test_buyer_can_create_public_service_request_with_attachment_and_matching_supplier(): void
    {
        Storage::fake('public');
        Queue::fake();
        Notification::fake();

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();
        [$seller, $listing] = $this->createSupplierListingForPort($port);
        $serviceFile = UploadedFile::fake()->create('scope-sheet.pdf', 32, 'application/pdf');

        $response = $this->actingAs($buyer)
            ->post(route('rfqs.store'), $this->serviceRequestPayload($port, [
                'reference_no' => 'RFQ-SERVICE-CREATE-001',
                'status' => 'open',
                'supplier_recipient_ids' => [$listing->id],
                'service_files' => [$serviceFile],
            ]));

        $response->assertRedirect(route('buyer.requests'));
        $response->assertSessionHas('success', 'rfq-created');

        $rfq = Rfq::query()
            ->where('reference_no', 'RFQ-SERVICE-CREATE-001')
            ->with(['attachments', 'supplierRecipients'])
            ->firstOrFail();

        $this->assertSame(Rfq::STATUS_SUBMITTED, $rfq->status);
        $this->assertSame(Rfq::VISIBILITY_PUBLIC_MARKETPLACE, $rfq->visibilityScope());
        $this->assertSame('service_request', $rfq->request_type);
        $this->assertNotNull($rfq->submitted_at);
        $this->assertCount(1, $rfq->attachments);
        $this->assertCount(1, $rfq->supplierRecipients);
        $this->assertSame($seller->id, $rfq->supplierRecipients->first()->seller_id);
        $this->assertSame($listing->id, $rfq->supplierRecipients->first()->supplier_service_listing_id);
        Storage::disk('public')->assertExists($rfq->attachments->first()->path);

        Queue::assertPushed(DispatchRfqDeliveryJob::class, function (DispatchRfqDeliveryJob $job) use ($rfq): bool {
            return $job->rfqId === $rfq->id;
        });
    }

    public function test_buyer_can_submit_public_service_request_even_when_no_matching_supplier_exists(): void
    {
        Queue::fake();
        Notification::fake();

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();

        $response = $this->actingAs($buyer)
            ->post(route('rfqs.store'), $this->serviceRequestPayload($port, [
                'reference_no' => 'RFQ-NO-MATCH-001',
                'status' => 'open',
                'supplier_recipient_ids' => [],
            ]));

        $response->assertRedirect(route('buyer.requests'));
        $response->assertSessionHas('success', 'rfq-created');

        $rfq = Rfq::query()
            ->where('reference_no', 'RFQ-NO-MATCH-001')
            ->with('supplierRecipients')
            ->firstOrFail();

        $this->assertSame(Rfq::STATUS_SUBMITTED, $rfq->status);
        $this->assertSame(Rfq::VISIBILITY_PUBLIC_MARKETPLACE, $rfq->visibilityScope());
        $this->assertCount(0, $rfq->supplierRecipients);

        Queue::assertNothingPushed();
    }

    public function test_supplier_targeted_private_request_still_requires_a_matching_supplier(): void
    {
        Queue::fake();
        Notification::fake();

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $albaniaPort = $this->createActivePort(
            countryName: 'Albania',
            countryCode: 'AL',
            portName: 'Durres',
            locationCode: 'DRZ',
            unlocode: 'ALDRZ',
        );
        $unmatchedAlbaniaPort = $this->createActivePort(
            countryName: 'Albania',
            countryCode: 'AL',
            portName: 'Shengjin',
            locationCode: 'SHG',
            unlocode: 'ALSHG',
        );
        $category = $this->createCategory();
        $subcategory = $this->createSubcategory($category);
        [$seller, $listing] = $this->createSupplierListingForPort($albaniaPort, $category, $subcategory);

        $response = $this->actingAs($buyer)
            ->from(route('rfqs.create', [
                'source' => 'supplier_detail',
                'supplier' => $seller->id,
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
            ]))
            ->post(route('rfqs.store', [
                'source' => 'supplier_detail',
                'supplier' => $seller->id,
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
            ]), $this->serviceRequestPayload($unmatchedAlbaniaPort, [
                'reference_no' => 'RFQ-PRIVATE-NO-MATCH-001',
                'status' => 'open',
                'category_ids' => [$category->id],
                'subcategory_ids' => [$subcategory->id],
                'supplier_recipient_ids' => [$listing->id],
            ]));

        $response->assertRedirect(route('rfqs.create', [
            'source' => 'supplier_detail',
            'supplier' => $seller->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
        ]));
        $response->assertSessionHasErrors([
            'supplier_recipient_ids' => 'No approved suppliers match this private request scope.',
        ]);

        $this->assertDatabaseMissing('rfqs', [
            'reference_no' => 'RFQ-PRIVATE-NO-MATCH-001',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_buyer_can_open_supplier_targeted_create_page_with_prefilled_private_context(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();
        $category = $this->createCategory();
        $subcategory = $this->createSubcategory($category);
        [, $listing] = $this->createSupplierListingForPort($port, $category, $subcategory);

        $expectedActionUrl = route('rfqs.store', [
            'source' => 'supplier_detail',
            'supplier' => $listing->seller_id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
        ]);

        $this->actingAs($buyer)
            ->get(route('rfqs.create', [
                'source' => 'supplier_detail',
                'supplier' => $listing->seller_id,
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/RFQ/Create/Page')
                ->where('actionUrl', $expectedActionUrl)
                ->where('defaults.request_type', 'service_request')
                ->where('defaults.category_ids', [$category->id])
                ->where('defaults.subcategory_ids', [$subcategory->id])
                ->where('defaults.supplier_recipient_ids', [$listing->id])
                ->where('supplierTarget.source', 'supplier_detail')
                ->where('supplierTarget.supplier_id', $listing->seller_id)
                ->where('supplierTarget.request_type_locked', true)
                ->where('supplierTarget.company_name', $listing->company_name)
                ->where('supplierTarget.category_ids', [$category->id])
                ->where('supplierTarget.subcategory_ids', [$subcategory->id])
                ->where('countryOptions', [$port->country_name])
                ->where('portsByCountry.'.$port->country_name.'.0.id', $port->id)
            );
    }

    public function test_supplier_targeted_service_request_is_saved_as_private_and_limited_to_selected_supplier(): void
    {
        Queue::fake();
        Notification::fake();

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();
        $category = $this->createCategory();
        $subcategory = $this->createSubcategory($category);
        [$seller, $listing] = $this->createSupplierListingForPort($port, $category, $subcategory);

        $response = $this->actingAs($buyer)
            ->post(route('rfqs.store', [
                'source' => 'supplier_detail',
                'supplier' => $seller->id,
                'category_id' => $category->id,
                'subcategory_id' => $subcategory->id,
            ]), $this->serviceRequestPayload($port, [
                'reference_no' => 'RFQ-PRIVATE-CREATE-001',
                'status' => 'open',
                'category_ids' => [$category->id],
                'subcategory_ids' => [$subcategory->id],
                'supplier_recipient_ids' => [$listing->id],
                'service_title' => 'Targeted onboard attendance',
            ]));

        $response->assertRedirect(route('buyer.requests'));
        $response->assertSessionHas('success', 'rfq-created');

        $rfq = Rfq::query()
            ->where('reference_no', 'RFQ-PRIVATE-CREATE-001')
            ->with('supplierRecipients')
            ->firstOrFail();

        $this->assertSame(Rfq::STATUS_SUBMITTED, $rfq->status);
        $this->assertSame(Rfq::VISIBILITY_PRIVATE_SUPPLIER, $rfq->visibilityScope());
        $this->assertCount(1, $rfq->supplierRecipients);
        $this->assertSame($seller->id, $rfq->supplierRecipients->first()->seller_id);
        $this->assertSame($listing->id, $rfq->supplierRecipients->first()->supplier_service_listing_id);
        $this->assertSame([$category->id], $rfq->category_ids);
        $this->assertSame([$subcategory->id], $rfq->subcategory_ids);

        Queue::assertPushed(DispatchRfqDeliveryJob::class, function (DispatchRfqDeliveryJob $job) use ($rfq): bool {
            return $job->rfqId === $rfq->id;
        });
    }

    private function sparePartsPayload(Port $port, array $overrides = []): array
    {
        $payload = [
            'request_type' => 'spare_parts',
            'reference_no' => 'RFQ-SPARE-BASE-001',
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Atlas',
            'country_names' => [$port->country_name],
            'ports_by_country' => [
                $port->country_name => [$port->id],
            ],
            'category_ids' => [],
            'subcategory_ids' => [],
            'brand_ids' => [],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => 'draft',
            'general_notes' => 'Spare parts request from feature test.',
            'service_title' => '',
            'service_description' => '',
            'service_files' => [],
            'supplier_recipient_ids' => [],
            'items' => [[
                'product_name' => 'Fuel oil filter element',
                'part_no' => 'FO-100',
                'quantity' => '4',
                'unit' => 'PCS',
                'manufacturer' => 'Fleetguard',
                'model_type' => 'FG-01',
                'serial_number' => 'SN-FO-1',
                'catalog_code' => 'CAT-FO-1',
                'rob' => '1',
                'drawing_number' => 'DRW-FO-1',
                'quality' => 'genuine',
                'comments' => 'Need class-approved supply.',
                'files' => [],
            ]],
        ];

        return array_replace_recursive($payload, $overrides);
    }

    private function serviceRequestPayload(Port $port, array $overrides = []): array
    {
        $payload = [
            'request_type' => 'service_request',
            'reference_no' => 'RFQ-SERVICE-BASE-001',
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Atlas',
            'country_names' => [$port->country_name],
            'ports_by_country' => [
                $port->country_name => [$port->id],
            ],
            'category_ids' => [],
            'subcategory_ids' => [],
            'brand_ids' => [],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'high',
            'status' => 'open',
            'general_notes' => 'Service request from feature test.',
            'service_title' => 'Hydraulic troubleshooting attendance',
            'service_description' => $this->longServiceDescription(),
            'service_files' => [],
            'supplier_recipient_ids' => [],
            'items' => [[
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
            ]],
        ];

        return array_replace_recursive($payload, $overrides);
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

    private function createCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Hydraulic Systems & Power Packs',
            'slug' => 'hydraulic-systems-power-packs',
            'has_subcategories' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createSubcategory(Category $category): Subcategory
    {
        return Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Hydraulic Pump Service',
            'slug' => 'hydraulic-pump-service',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createSupplierListingForPort(Port $port, ?Category $category = null, ?Subcategory $subcategory = null): array
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'Atlas Marine Service',
            'country' => $port->country_name,
            'service_country_codes' => [$port->country_code],
        ]);

        $seller->servicePorts()->attach($port->id);

        $listing = SupplierServiceListing::query()->create([
            'seller_id' => $seller->id,
            'listing_key' => 'atlas-service-'.Str::uuid(),
            'company_name' => $seller->company_name,
            'contact_name' => $seller->name,
            'country' => $port->country_name,
            'summary' => 'Rapid onboard service attendance.',
            'category_id' => $category?->id,
            'category_name' => $category?->name,
            'category_slug' => $category?->slug,
            'subcategory_id' => $subcategory?->id,
            'subcategory_name' => $subcategory?->name,
            'subcategory_slug' => $subcategory?->slug,
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

    private function longServiceDescription(): string
    {
        return str_repeat('Detailed onboard service scope with attendance, diagnosis, repair plan, and reporting. ', 5);
    }
}
