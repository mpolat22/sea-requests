<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Port;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use App\Support\SupplierServiceListingIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ServicesDirectorySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_directory_search_matches_supplier_brand_and_port_terms(): void
    {
        [$seller, $brand, $port] = $this->createSearchableSupplier();

        $this->assertSame(1, SupplierServiceListing::query()->where('search_text', 'like', '%abb%')->count());
        $this->assertSame(1, SupplierServiceListing::query()->where('search_text', 'like', '%abu dhabi%')->count());
        $this->assertSame(1, SupplierServiceListing::query()->where('search_text', 'like', '%aeauh%')->count());

        $this->get('/services?search=ABB')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Service/ServicesIndex')
                ->where('filters.search', 'ABB')
                ->where('suppliersPage.total', 1)
                ->where('suppliersPage.data.0.company_name', $seller->company_name)
            );

        $this->get('/services?search=Abu%20Dhabi')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Service/ServicesIndex')
                ->where('filters.search', 'Abu Dhabi')
                ->where('suppliersPage.total', 1)
                ->where('suppliersPage.data.0.company_name', $seller->company_name)
            );

        $this->get('/services?search=AEAUH')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Service/ServicesIndex')
                ->where('filters.search', 'AEAUH')
                ->where('suppliersPage.total', 1)
                ->where('suppliersPage.data.0.company_name', $seller->company_name)
            );
    }

    public function test_services_directory_search_matches_company_names_with_hyphenated_terms(): void
    {
        $category = Category::query()->create([
            'name' => 'Repair Services',
            'slug' => 'repair-services',
            'has_subcategories' => true,
            'is_active' => true,
        ]);

        $subcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Engine Overhaul',
            'slug' => 'engine-overhaul',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'approved_at' => now(),
            'name' => 'Demo Contact',
            'company_name' => 'Demo Supplier-4',
            'company_overview' => 'Engine overhaul services.',
            'country' => 'TR',
            'service_category_ids' => [$category->id],
            'service_subcategory_ids' => [$subcategory->id],
            'service_subcategories_by_category' => [(string) $category->id => [$subcategory->id]],
            'service_brand_ids' => [],
            'service_country_codes' => ['TR'],
        ]);

        app(SupplierServiceListingIndex::class)->syncSeller($seller);

        $this->get('/services?search=Demo%20Supplier-4')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Service/ServicesIndex')
                ->where('filters.search', 'Demo Supplier-4')
                ->where('suppliersPage.total', 1)
                ->where('suppliersPage.data.0.company_name', 'Demo Supplier-4')
            );

        $this->get('/services?search=Demo%20Supplier%204')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Service/ServicesIndex')
                ->where('filters.search', 'Demo Supplier 4')
                ->where('suppliersPage.total', 1)
                ->where('suppliersPage.data.0.company_name', 'Demo Supplier-4')
            );
    }

    public function test_services_directory_lists_each_supplier_once_even_with_multiple_subcategory_matches(): void
    {
        $category = Category::query()->create([
            'name' => 'Repair Services',
            'slug' => 'repair-services',
            'has_subcategories' => true,
            'is_active' => true,
        ]);

        $firstSubcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Engine Overhaul',
            'slug' => 'engine-overhaul',
            'is_active' => true,
        ]);

        $secondSubcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Pump Repair',
            'slug' => 'pump-repair',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'approved_at' => now(),
            'name' => 'Atlas Contact',
            'company_name' => 'Atlas Marine Service',
            'company_overview' => 'Repair services for engines and pumps.',
            'country' => 'TR',
            'service_category_ids' => [$category->id],
            'service_subcategory_ids' => [$firstSubcategory->id, $secondSubcategory->id],
            'service_subcategories_by_category' => [(string) $category->id => [$firstSubcategory->id, $secondSubcategory->id]],
            'service_brand_ids' => [],
            'service_country_codes' => ['TR'],
        ]);

        app(SupplierServiceListingIndex::class)->syncSeller($seller);

        $this->assertSame(2, SupplierServiceListing::query()->where('seller_id', $seller->id)->count());

        $this->get('/services')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Service/ServicesIndex')
                ->where('suppliersPage.total', 1)
                ->where('suppliersPage.data.0.company_name', 'Atlas Marine Service')
            );

        $this->get('/services?parentCategories%5B0%5D=repair-services&subcategories%5B0%5D=pump-repair')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Service/ServicesIndex')
                ->where('suppliersPage.total', 1)
                ->where('suppliersPage.data.0.company_name', 'Atlas Marine Service')
                ->where('suppliersPage.data.0.secondary_category.slug', 'pump-repair')
            );
    }

    /**
     * @return array{0: User, 1: Brand, 2: Port}
     */
    private function createSearchableSupplier(): array
    {
        $category = Category::query()->create([
            'name' => 'Calibration & Testing Services',
            'slug' => 'calibration-testing-services',
            'has_subcategories' => true,
            'is_active' => true,
        ]);

        $subcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name' => 'Pressure Gauge',
            'slug' => 'pressure-gauge',
            'is_active' => true,
        ]);

        $brand = Brand::query()->create([
            'name' => 'ABB Marine',
            'slug' => 'abb-marine',
            'is_active' => true,
        ]);

        $port = Port::query()->create([
            'country_code' => 'AE',
            'country_name' => 'United Arab Emirates',
            'port_name' => 'Abu Dhabi',
            'unlocode' => 'AEAUH',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'approved_at' => now(),
            'name' => 'Atlas Contact',
            'company_name' => 'Atlas Marine Service',
            'company_overview' => 'Calibration and testing supplier.',
            'country' => 'AE',
            'service_category_ids' => [$category->id],
            'service_subcategory_ids' => [$subcategory->id],
            'service_subcategories_by_category' => [(string) $category->id => [$subcategory->id]],
            'service_brand_ids' => [$brand->id],
            'service_country_codes' => ['AE'],
        ]);

        $seller->servicePorts()->sync([$port->id]);

        app(SupplierServiceListingIndex::class)->syncSeller($seller);

        return [$seller, $brand, $port];
    }
}
