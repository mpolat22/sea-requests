<?php

namespace Tests\Feature;

use App\Models\Port;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BuyerRfqEditAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_buyer_edit_page_shows_existing_service_request_attachments(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();
        $rfq = $this->createServiceRequestDraft($buyer, $port);

        $storedFile = UploadedFile::fake()->create('scope-sheet.pdf', 24, 'application/pdf');
        $path = $storedFile->store("rfqs/{$rfq->id}/attachments", 'public');

        $rfq->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'scope-sheet.pdf',
            'mime_type' => 'application/pdf',
            'size' => $storedFile->getSize(),
        ]);

        $this->actingAs($buyer)
            ->get(route('rfqs.edit', $rfq))
            ->assertOk()
            ->assertSee('scope-sheet.pdf')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('defaults.service_files.0.name', 'scope-sheet.pdf')
                ->where('defaults.service_files.0.mime_type', 'application/pdf')
            );
    }

    public function test_buyer_edit_page_shows_existing_spare_parts_item_attachments(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();
        [$rfq, $item] = $this->createSparePartsDraft($buyer, $port);

        $storedFile = UploadedFile::fake()->create('pump-photo.png', 18, 'image/png');
        $path = $storedFile->store("rfqs/{$rfq->id}/items/{$item->id}", 'public');

        $item->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'pump-photo.png',
            'mime_type' => 'image/png',
            'size' => $storedFile->getSize(),
        ]);

        $this->actingAs($buyer)
            ->get(route('rfqs.edit', $rfq))
            ->assertOk()
            ->assertSee('pump-photo.png');
    }

    public function test_buyer_can_remove_existing_service_request_attachment_during_edit(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();
        $rfq = $this->createServiceRequestDraft($buyer, $port);

        $firstFile = UploadedFile::fake()->create('scope-a.pdf', 20, 'application/pdf');
        $secondFile = UploadedFile::fake()->create('scope-b.pdf', 20, 'application/pdf');

        $firstAttachment = $rfq->attachments()->create([
            'disk' => 'public',
            'path' => $firstFile->store("rfqs/{$rfq->id}/attachments", 'public'),
            'original_name' => 'scope-a.pdf',
            'mime_type' => 'application/pdf',
            'size' => $firstFile->getSize(),
        ]);

        $secondAttachment = $rfq->attachments()->create([
            'disk' => 'public',
            'path' => $secondFile->store("rfqs/{$rfq->id}/attachments", 'public'),
            'original_name' => 'scope-b.pdf',
            'mime_type' => 'application/pdf',
            'size' => $secondFile->getSize(),
        ]);

        $response = $this->actingAs($buyer)
            ->put(route('rfqs.update', $rfq), [
                'request_type' => 'service_request',
                'reference_no' => $rfq->reference_no,
                'company_name' => $rfq->company_name,
                'ship_name' => $rfq->ship_name,
                'imo_number' => $rfq->imo_number ?: '1234567',
                'country_names' => ['Albania'],
                'ports_by_country' => [
                    'Albania' => [$port->id],
                ],
                'category_ids' => [],
                'subcategory_ids' => [],
                'brand_ids' => [],
                'requisition_date' => now()->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'currency' => 'USD',
                'priority' => 'normal',
                'status' => 'draft',
                'general_notes' => 'Updated notes',
                'service_title' => 'Service scope update',
                'service_description' => str_repeat('Detailed service scope for edit flow. ', 8),
                'existing_service_attachment_ids' => [$secondAttachment->id],
                'supplier_recipient_ids' => [],
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
            ]);

        $response->assertRedirect(route('buyer.requests'));

        $this->assertDatabaseMissing('rfq_attachments', [
            'id' => $firstAttachment->id,
        ]);

        $this->assertDatabaseHas('rfq_attachments', [
            'id' => $secondAttachment->id,
        ]);

        Storage::disk('public')->assertMissing($firstAttachment->path);
        Storage::disk('public')->assertExists($secondAttachment->path);
    }

    public function test_buyer_can_remove_existing_spare_parts_item_attachment_during_edit(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $port = $this->createActivePort();
        [$rfq, $item] = $this->createSparePartsDraft($buyer, $port);

        $firstFile = UploadedFile::fake()->create('part-a.pdf', 20, 'application/pdf');
        $secondFile = UploadedFile::fake()->create('part-b.pdf', 20, 'application/pdf');

        $firstAttachment = $item->attachments()->create([
            'disk' => 'public',
            'path' => $firstFile->store("rfqs/{$rfq->id}/items/{$item->id}", 'public'),
            'original_name' => 'part-a.pdf',
            'mime_type' => 'application/pdf',
            'size' => $firstFile->getSize(),
        ]);

        $secondAttachment = $item->attachments()->create([
            'disk' => 'public',
            'path' => $secondFile->store("rfqs/{$rfq->id}/items/{$item->id}", 'public'),
            'original_name' => 'part-b.pdf',
            'mime_type' => 'application/pdf',
            'size' => $secondFile->getSize(),
        ]);

        $response = $this->actingAs($buyer)
            ->put(route('rfqs.update', $rfq), [
                'request_type' => 'spare_parts',
                'reference_no' => $rfq->reference_no,
                'company_name' => $rfq->company_name,
                'ship_name' => $rfq->ship_name,
                'imo_number' => $rfq->imo_number ?: '1234567',
                'country_names' => ['Albania'],
                'ports_by_country' => [
                    'Albania' => [$port->id],
                ],
                'category_ids' => [],
                'subcategory_ids' => [],
                'brand_ids' => [],
                'requisition_date' => now()->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'currency' => 'USD',
                'priority' => 'normal',
                'status' => 'draft',
                'general_notes' => 'Updated notes',
                'service_title' => '',
                'service_description' => '',
                'service_files' => [],
                'supplier_recipient_ids' => [],
                'items' => [
                    [
                        'id' => $item->id,
                        'product_name' => 'Cylinder liner',
                        'part_no' => 'CL-100',
                        'quantity' => '2',
                        'unit' => 'PCS',
                        'manufacturer' => 'Maker',
                        'model_type' => 'Model A',
                        'serial_number' => '',
                        'catalog_code' => '',
                        'rob' => '',
                        'drawing_number' => '',
                        'quality' => 'genuine',
                        'comments' => 'Keep one existing file',
                        'existing_attachment_ids' => [$secondAttachment->id],
                    ],
                ],
            ]);

        $response->assertRedirect(route('buyer.requests'));

        $this->assertDatabaseMissing('rfq_item_attachments', [
            'id' => $firstAttachment->id,
        ]);

        $this->assertDatabaseHas('rfq_item_attachments', [
            'id' => $secondAttachment->id,
        ]);

        Storage::disk('public')->assertMissing($firstAttachment->path);
        Storage::disk('public')->assertExists($secondAttachment->path);
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

    private function createServiceRequestDraft(User $buyer, Port $port): Rfq
    {
        return Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SERVICE-EDIT-001',
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Buyer',
            'imo_number' => '1234567',
            'request_type' => 'service_request',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => 'Albania',
            'port_name' => 'Durres',
            'country_names' => ['Albania'],
            'ports_by_country' => [
                'Albania' => [
                    ['id' => $port->id, 'name' => 'Durres', 'unlocode' => 'ALDRZ'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_DRAFT,
            'general_notes' => 'Draft service request',
            'service_title' => 'Service scope',
            'service_description' => str_repeat('Detailed service scope for edit flow. ', 8),
            'items_count' => 1,
        ]);
    }

    private function createSparePartsDraft(User $buyer, Port $port): array
    {
        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-SPARE-EDIT-001',
            'company_name' => 'Buyer Company',
            'ship_name' => 'MV Buyer',
            'imo_number' => '1234567',
            'request_type' => 'spare_parts',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => 'Albania',
            'port_name' => 'Durres',
            'country_names' => ['Albania'],
            'ports_by_country' => [
                'Albania' => [
                    ['id' => $port->id, 'name' => 'Durres', 'unlocode' => 'ALDRZ'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_DRAFT,
            'general_notes' => 'Draft spare parts request',
            'items_count' => 1,
        ]);

        $item = RfqItem::query()->create([
            'rfq_id' => $rfq->id,
            'line_no' => 1,
            'product_name' => 'Cylinder liner',
            'part_no' => 'CL-100',
            'quantity' => 2,
            'unit' => 'PCS',
            'manufacturer' => 'Maker',
            'model_type' => 'Model A',
            'serial_number' => null,
            'catalog_code' => null,
            'rob' => null,
            'drawing_number' => null,
            'quality' => 'genuine',
            'comments' => 'Initial spare part line',
        ]);

        return [$rfq, $item];
    }
}
