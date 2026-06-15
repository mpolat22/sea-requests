<?php

namespace Tests\Feature;

use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerWorkspaceReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_seller_is_redirected_to_verification_notice_from_seller_workspace(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => null,
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_seller_with_missing_verification_profile_is_redirected_to_verification_form(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($seller)
            ->get(route('seller.orders'))
            ->assertRedirect(route('seller.verification.create'));
    }

    public function test_seller_with_submitted_profile_but_pending_approval_is_redirected_to_pending_page(): void
    {
        $port = Port::query()->create([
            'unlocode' => 'TRIST',
            'country_code' => 'TR',
            'location_code' => 'IST',
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
            'approval_status' => 'pending',
            'approved_at' => null,
            'country' => 'Turkey',
            'phone' => '+90 5551234567',
            'contact_email' => 'seller-pending@example.test',
            'company_address_line' => 'Harbor Center 2',
            'company_city' => 'Istanbul',
            'company_overview' => str_repeat('Approved profile content ', 12),
            'company_logo_path' => 'seller-media/demo/logo.png',
            'service_category_ids' => [1],
            'service_country_codes' => ['TR'],
            'registration_number' => 'TR-READY-001',
            'company_registration_documents' => [['path' => 'seller-media/demo/company.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        $this->actingAs($seller)
            ->get(route('seller.requests'))
            ->assertRedirect(route('approval.pending'));
    }

    public function test_submitted_seller_profile_with_missing_contact_fields_is_redirected_back_to_verification_form(): void
    {
        $port = Port::query()->create([
            'unlocode' => 'ALDRZ',
            'country_code' => 'AL',
            'location_code' => 'DRZ',
            'country_name' => 'Albania',
            'port_name' => 'Durres',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
            'approval_status' => 'approved',
            'approved_at' => now(),
            'country' => 'Albania',
            'phone' => null,
            'contact_email' => 'seller-ready@example.test',
            'company_address_line' => 'Port Logistics Building',
            'company_city' => 'Durres',
            'company_overview' => str_repeat('Ready profile content ', 12),
            'company_logo_path' => 'seller-media/demo/logo.png',
            'service_category_ids' => [1],
            'service_country_codes' => ['AL'],
            'registration_number' => 'AL-READY-001',
            'company_registration_documents' => [['path' => 'seller-media/demo/company.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ]);

        $seller->servicePorts()->sync([$port->id]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertRedirect(route('seller.verification.create'));
    }
}
