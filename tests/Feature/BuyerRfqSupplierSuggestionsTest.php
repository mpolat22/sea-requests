<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerRfqSupplierSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_request_supplier_suggestions_do_not_error_when_brand_is_detected_from_service_text(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        Brand::query()->create([
            'name' => 'Fleetguard',
            'slug' => 'fleetguard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Category::query()->create([
            'name' => 'Filters',
            'slug' => 'filters',
            'is_active' => true,
            'sort_order' => 1,
            'has_subcategories' => true,
        ]);

        $response = $this->actingAs($buyer)
            ->postJson(route('rfqs.supplier-suggestions'), [
                'request_type' => 'service_request',
                'service_title' => 'Fleetguard filter replacement attendance',
                'service_description' => str_repeat('Fleetguard filter replacement service at anchorage with onboard inspection and reporting. ', 3),
                'items' => [],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'filters' => ['category_ids', 'subcategory_ids', 'brand_ids'],
                'brands',
                'categories',
                'subcategories',
                'row_suggestions',
                'document_context',
                'summary',
                'empty_message',
            ]);
    }

    public function test_spare_parts_supplier_suggestions_return_row_suggestions_for_item_payload(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $category = Category::query()->create([
            'name' => 'Engine & Mechanical Systems',
            'slug' => 'engine-mechanical-systems',
            'is_active' => true,
            'sort_order' => 1,
            'has_subcategories' => true,
        ]);

        Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Cylinder Liner',
            'slug' => 'cylinder-liner',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Brand::query()->create([
            'name' => 'MAN B&W',
            'slug' => 'man-bw',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($buyer)
            ->postJson(route('rfqs.supplier-suggestions'), [
                'request_type' => 'spare_parts',
                'items' => [[
                    'product_name' => 'Cylinder liner',
                    'part_no' => 'CL-100',
                    'manufacturer' => 'MAN B&W',
                    'model_type' => '6S50MC',
                    'catalog_code' => 'CAT-100',
                    'comments' => 'Urgent requirement',
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('row_suggestions.0.source', 'Cylinder liner');
    }
}
