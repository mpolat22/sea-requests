<?php

namespace Tests\Feature;

use App\Models\Port;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RequestShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_request_show_includes_top_level_attachment_mime_type(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => 'buyer']);
        $port = $this->createActivePort();

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-PUBLIC-SHOW-001',
            'company_name' => 'Northwind Buyer',
            'ship_name' => 'MV Horizon',
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
            'general_notes' => 'Public request note.',
            'service_title' => 'Fresh Water Supply',
            'service_description' => 'Fresh water supply attendance requested at berth.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $storedFile = UploadedFile::fake()->create('scope-sheet.pdf', 24, 'application/pdf');
        $path = $storedFile->store("rfqs/{$rfq->id}/attachments", 'public');

        $rfq->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'scope-sheet.pdf',
            'mime_type' => 'application/pdf',
            'size' => $storedFile->getSize(),
        ]);

        $this->get(route('rfqs.show', ['rfq' => $rfq, 'slug' => $rfq->publicSlug()]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Request/RequestShow')
                ->where('rfq.reference_no', 'RFQ-PUBLIC-SHOW-001')
                ->where('rfq.attachments.0.name', 'scope-sheet.pdf')
                ->where('rfq.attachments.0.mime_type', 'application/pdf')
            );
    }

    public function test_public_request_show_includes_item_attachment_mime_type(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => 'buyer']);
        $port = $this->createActivePort();

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-PUBLIC-SHOW-002',
            'company_name' => 'Northwind Buyer',
            'ship_name' => 'MV Horizon',
            'request_type' => 'spare_parts',
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
            'general_notes' => 'Public spare request note.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $item = RfqItem::query()->create([
            'rfq_id' => $rfq->id,
            'line_no' => 1,
            'product_name' => 'Pump overhaul kit',
            'part_no' => 'PK-100',
            'quantity' => 2,
            'unit' => 'PCS',
            'manufacturer' => 'MAN',
            'model_type' => 'B&W',
            'serial_number' => 'SER-100',
            'catalog_code' => 'CAT-100',
            'rob' => 1,
            'drawing_number' => 'DWG-100',
            'quality' => 'genuine',
            'comments' => 'Need quotation and lead time.',
        ]);

        $storedFile = UploadedFile::fake()->image('pump-photo.png');
        $path = $storedFile->store("rfqs/{$rfq->id}/items/{$item->id}", 'public');

        $item->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'pump-photo.png',
            'mime_type' => 'image/png',
            'size' => $storedFile->getSize(),
        ]);

        $this->get(route('rfqs.show', ['rfq' => $rfq, 'slug' => $rfq->publicSlug()]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Request/RequestShow')
                ->where('rfq.items.0.product_name', 'Pump overhaul kit')
                ->where('rfq.items.0.attachments.0.name', 'pump-photo.png')
                ->where('rfq.items.0.attachments.0.mime_type', 'image/png')
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
}
