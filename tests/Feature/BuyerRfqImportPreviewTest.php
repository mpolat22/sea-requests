<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RfqImportAiRefiner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\TestCase;

class BuyerRfqImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_import_preview_can_fall_back_to_ai_using_page_images(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $expectedPreview = [
            'summary' => [
                'file_name' => 'scan.pdf',
                'sheet_name' => 'Scanned RFQ',
                'source_type' => 'pdf',
                'items_count' => 1,
                'mapped_columns' => ['product_name', 'quantity', 'unit'],
                'review_count' => 2,
                'ai_refined' => true,
                'ai_recovered' => false,
                'ai_first_extracted' => true,
            ],
            'general' => [
                'ship_name' => 'MV Horizon',
            ],
            'mapping' => [
                'general' => [],
                'items' => [],
            ],
            'confidence' => [
                'general' => [],
                'items' => [],
            ],
            'items' => [[
                'product_name' => 'Fuel oil filter element',
                'part_no' => '',
                'manufacturer' => 'Fleetguard',
                'model_type' => '',
                'catalog_code' => '',
                'serial_number' => '',
                'drawing_number' => '',
                'quantity' => '4',
                'unit' => 'PCS',
                'rob' => '',
                'quality' => '',
                'comments' => '',
                'files' => [],
            ]],
            'raw' => [
                'general_pairs' => [],
                'item_columns' => [],
                'item_rows' => [],
            ],
        ];

        $this->mock(RfqImportAiRefiner::class, function (MockInterface $mock) use ($expectedPreview) {
            $mock->shouldReceive('extractFromDocumentImages')
                ->once()
                ->withArgs(function (
                    array $images,
                    string $fileName,
                    string $sheetName,
                    string $sourceType,
                    array $aliases,
                    array $ocrLines,
                    array $ocrRows
                ): bool {
                    return count($images) === 1
                        && str_starts_with($images[0], 'data:image/jpeg;base64,')
                        && $fileName === 'scan.pdf'
                        && $sheetName === 'Scanned RFQ'
                        && $sourceType === 'pdf'
                        && $aliases === ['general' => [], 'items' => []]
                        && $ocrLines === []
                        && $ocrRows === [];
                })
                ->andReturn($expectedPreview);

            $mock->shouldReceive('extractBestPreviewFromRows')->never();
            $mock->shouldReceive('recoverFromRows')->never();
            $mock->shouldReceive('refinePreview')->never();
            $mock->shouldReceive('extractFromImageFile')->never();
        });

        $response = $this->actingAs($buyer)->post(route('rfqs.import-preview'), [
            'file' => UploadedFile::fake()->create('scan.pdf', 24, 'application/pdf'),
            'rows_payload' => json_encode([]),
            'ocr_lines_payload' => json_encode([]),
            'page_images_payload' => json_encode([
                'data:image/jpeg;base64,'.base64_encode('fake-page-image'),
            ]),
            'sheet_name' => 'Scanned RFQ',
            'source_type' => 'pdf',
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.source_type', 'pdf')
            ->assertJsonPath('summary.ai_first_extracted', true)
            ->assertJsonPath('items.0.product_name', 'Fuel oil filter element')
            ->assertJsonPath('items.0.quantity', '4');
    }
}
