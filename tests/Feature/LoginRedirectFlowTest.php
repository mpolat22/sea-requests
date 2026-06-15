<?php

namespace Tests\Feature;

use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_login_redirects_directly_to_buyer_requests(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'email' => 'buyer@example.test',
        ]);

        $this->post(route('login'), [
            'email' => 'buyer@example.test',
            'password' => 'password',
        ])->assertRedirect(route('buyer.requests'));
    }

    public function test_buyer_login_keeps_valid_next_destination_without_dashboard_bridge(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'email' => 'buyer-next@example.test',
        ]);

        $this->post(route('login'), [
            'email' => 'buyer-next@example.test',
            'password' => 'password',
            'next' => '/requests',
        ])->assertRedirect('/requests');
    }

    public function test_ready_seller_login_redirects_directly_to_seller_dashboard(): void
    {
        $seller = $this->createReadySeller([
            'email' => 'seller@example.test',
        ]);

        $this->post(route('login'), [
            'email' => 'seller@example.test',
            'password' => 'password',
        ])->assertRedirect(route('seller.dashboard'));
    }

    public function test_unverified_seller_login_redirects_directly_to_verification_notice(): void
    {
        $seller = $this->createReadySeller([
            'email' => 'seller-unverified@example.test',
            'email_verified_at' => null,
        ]);

        $this->post(route('login'), [
            'email' => 'seller-unverified@example.test',
            'password' => 'password',
            'next' => '/seller/orders',
        ])->assertRedirect(route('verification.notice'));

        $this->assertSame('/seller/orders', session('auth.next'));
    }

    private function createReadySeller(array $overrides = []): User
    {
        $port = Port::query()->create([
            'name' => 'Durres',
            'port_name' => 'Durres',
            'country_name' => 'Albania',
            'country_code' => 'AL',
            'unlocode' => 'ALDRZ',
            'is_active' => true,
            'latitude' => 41.3231,
            'longitude' => 19.4540,
        ]);

        $seller = User::factory()->create(array_merge([
            'role' => 'seller',
            'country' => $port->country_name,
            'email_verified_at' => now(),
            'approval_status' => 'approved',
            'approved_at' => now(),
            'phone' => '+355 681234567',
            'contact_email' => 'seller-ready@example.test',
            'company_address_line' => 'Dock Office 14',
            'company_city' => $port->port_name,
            'company_overview' => str_repeat('Supplier ready profile. ', 12),
            'company_logo_path' => 'seller-media/demo/logo.png',
            'service_category_ids' => [1],
            'service_country_codes' => [$port->country_code],
            'registration_number' => "{$port->country_code}-READY-001",
            'company_registration_documents' => [['path' => 'seller-media/demo/company.pdf', 'name' => 'company.pdf']],
            'seller_verification_submitted_at' => now(),
        ], $overrides));

        $seller->servicePorts()->sync([$port->id]);

        return $seller;
    }
}
