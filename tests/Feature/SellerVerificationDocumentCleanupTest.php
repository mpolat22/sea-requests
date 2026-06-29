<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Port;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerVerificationDocumentCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_seller_verification_clears_legacy_tax_and_authorization_documents(): void
    {
        Storage::fake('public');

        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
            'approval_status' => 'pending',
            'approved_at' => null,
            'seller_verification_submitted_at' => null,
            'company_logo_path' => null,
            'tax_certificate_documents' => [
                ['path' => 'seller-media/demo/legacy-tax.pdf', 'name' => 'legacy-tax.pdf'],
            ],
            'service_authorization_documents' => [
                ['path' => 'seller-media/demo/legacy-auth.pdf', 'name' => 'legacy-auth.pdf'],
            ],
            'tax_certificate_document_path' => 'seller-media/demo/legacy-tax.pdf',
            'service_authorization_document_path' => 'seller-media/demo/legacy-auth.pdf',
        ]);

        $category = Category::create([
            'name' => 'Calibration & Testing Services',
            'slug' => 'calibration-testing-services',
            'has_subcategories' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Pressure Gauge',
            'slug' => 'pressure-gauge',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $port = Port::create([
            'country_code' => 'TR',
            'country_name' => 'Turkey',
            'location_code' => 'IST',
            'port_name' => 'Istanbul',
            'unlocode' => 'TRIST',
            'is_active' => true,
        ]);

        $payload = [
            'company_name' => 'Sea Requests Test Supplier',
            'country' => 'Turkey',
            'company_city' => 'Istanbul',
            'company_postal_code' => '34947',
            'company_address_line' => 'Pendik Marina Office Block 2 Istanbul',
            'service_category_ids' => [$category->id],
            'service_subcategory_ids' => [$subcategory->id],
            'service_subcategories_by_category' => [
                (string) $category->id => [$subcategory->id],
            ],
            'service_country_codes' => ['TR'],
            'service_ports_by_country' => [
                'TR' => [$port->id],
            ],
            'phone' => '+90 5550000000',
            'landline_phone' => '5550000000',
            'contact_email' => 'supplier@example.com',
            'company_overview' => str_repeat('A', 220),
            'registration_number' => 'REG-12345',
            'company_logo' => UploadedFile::fake()->image('logo.png'),
            'company_registration_documents' => [
                UploadedFile::fake()->create('company-registration.pdf', 120, 'application/pdf'),
            ],
        ];

        $this->actingAs($seller)
            ->post(route('seller.verification.store'), $payload)
            ->assertRedirect(route('approval.pending'));

        $seller->refresh();

        $this->assertSame([], $seller->tax_certificate_documents ?? []);
        $this->assertSame([], $seller->service_authorization_documents ?? []);
        $this->assertNull($seller->tax_certificate_document_path);
        $this->assertNull($seller->service_authorization_document_path);
        $this->assertNotEmpty($seller->company_registration_documents ?? []);
    }
}
