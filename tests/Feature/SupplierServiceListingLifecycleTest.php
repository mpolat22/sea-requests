<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Port;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use App\Support\SupplierServiceListingIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SupplierServiceListingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_appears_in_services_directory_after_admin_approval(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

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

        $port = Port::query()->create([
            'country_code' => 'AE',
            'country_name' => 'United Arab Emirates',
            'port_name' => 'Abu Dhabi',
            'unlocode' => 'AEAUH',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'pending',
            'approved_at' => null,
            'name' => 'Atlas Contact',
            'company_name' => 'Atlas Marine Service',
            'company_overview' => str_repeat('Calibration and testing services. ', 10),
            'company_description' => 'Calibration and testing services.',
            'country' => 'AE',
            'company_address_line' => 'Harbor Road 1',
            'company_city' => 'Abu Dhabi',
            'phone' => '+971500000000',
            'contact_email' => 'atlas@example.test',
            'company_logo_path' => 'logos/atlas.png',
            'registration_number' => 'REG-ATLAS-001',
            'service_category_ids' => [$category->id],
            'service_subcategory_ids' => [$subcategory->id],
            'service_subcategories_by_category' => [(string) $category->id => [$subcategory->id]],
            'service_brand_ids' => [],
            'service_country_codes' => ['AE'],
            'company_registration_documents' => [
                ['name' => 'reg.pdf', 'path' => 'docs/reg.pdf'],
            ],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        $this->assertSame(0, SupplierServiceListing::query()->count());

        $this->actingAs($admin)
            ->patch(route('admin.users.approval', $seller), [
                'action' => 'approve',
            ])
            ->assertRedirect();

        $this->assertTrue($seller->fresh()->isApproved());
        $this->assertSame(1, SupplierServiceListing::query()->where('seller_id', $seller->id)->count());

        $this->get('/services')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Service/ServicesIndex')
                ->where('suppliersPage.total', 1)
                ->where('suppliersPage.data.0.company_name', 'Atlas Marine Service')
            );
    }

    public function test_changing_seller_role_to_buyer_clears_service_directory_listing(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        [$seller] = $this->createApprovedIndexedSeller();

        $this->assertSame(1, SupplierServiceListing::query()->where('seller_id', $seller->id)->count());

        $this->actingAs($admin)
            ->patch(route('admin.users.profile.update', $seller), [
                'name' => $seller->name,
                'email' => $seller->email,
                'role' => 'buyer',
            ])
            ->assertRedirect();

        $this->assertSame(0, SupplierServiceListing::query()->where('seller_id', $seller->id)->count());
    }

    public function test_admin_can_view_supplier_contact_details_on_public_service_profile(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        [$seller] = $this->createApprovedIndexedSeller();

        $listing = SupplierServiceListing::query()
            ->where('seller_id', $seller->id)
            ->firstOrFail(['category_slug', 'subcategory_slug', 'vendor_slug']);

        $this->actingAs($admin)
            ->get(route('services.show', [
                'category' => $listing->category_slug,
                'subcategory' => $listing->subcategory_slug ?: $listing->category_slug,
                'vendor' => $listing->vendor_slug,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Service/ServiceShow')
                ->where('service.can_view_contact_details', true)
                ->where('service.contact_access_state', 'granted')
                ->where('service.vendor.email', $seller->contact_email)
                ->where('service.vendor.phone', $seller->phone)
                ->where('service.vendor.address', $seller->company_address)
            );
    }

    /**
     * @return array{0: User, 1: Port}
     */
    private function createApprovedIndexedSeller(): array
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

        $port = Port::query()->create([
            'country_code' => 'TR',
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
            'unlocode' => 'TRIST',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'approved_at' => now(),
            'name' => 'Demo Contact',
            'company_name' => 'Demo Supplier',
            'company_overview' => 'Engine overhaul services.',
            'country' => 'TR',
            'company_city' => 'Istanbul',
            'company_address' => 'Shipyard Avenue 12, Istanbul, Turkey',
            'company_address_line' => 'Shipyard Avenue 12',
            'phone' => '+905551112233',
            'contact_email' => 'contact@demosupplier.test',
            'service_category_ids' => [$category->id],
            'service_subcategory_ids' => [$subcategory->id],
            'service_subcategories_by_category' => [(string) $category->id => [$subcategory->id]],
            'service_brand_ids' => [],
            'service_country_codes' => ['TR'],
        ]);

        $seller->servicePorts()->sync([$port->id]);

        app(SupplierServiceListingIndex::class)->syncSeller($seller);

        return [$seller, $port];
    }
}
