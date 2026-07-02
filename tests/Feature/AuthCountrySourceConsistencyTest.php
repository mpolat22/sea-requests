<?php

namespace Tests\Feature;

use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuthCountrySourceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_uses_active_port_countries_for_country_and_dial_code_dropdowns(): void
    {
        Port::query()->create([
            'country_code' => 'GH',
            'location_code' => 'TEM',
            'unlocode' => 'GHTEM',
            'country_name' => 'Ghana',
            'port_name' => 'Tema',
            'is_active' => true,
        ]);

        Port::query()->create([
            'country_code' => 'PA',
            'location_code' => 'BLB',
            'unlocode' => 'PABLB',
            'country_name' => 'Panama',
            'port_name' => 'Balboa',
            'is_active' => true,
        ]);

        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Register')
                ->where('countryOptions', fn ($options) => collect($options)->pluck('value')->all() === ['Ghana', 'Panama'])
                ->where('dialCodeOptions', fn ($options) => collect($options)->pluck('label')->all() === ['Ghana (+233)', 'Panama (+507)'])
            );
    }

    public function test_seller_verification_page_uses_active_port_countries_for_service_country_and_dial_code_source(): void
    {
        Port::query()->create([
            'country_code' => 'GH',
            'location_code' => 'TEM',
            'unlocode' => 'GHTEM',
            'country_name' => 'Ghana',
            'port_name' => 'Tema',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'pending',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($seller)
            ->get(route('seller.verification.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/SupplierVerification')
                ->where('serviceCountries', fn ($countries) => collect($countries)->pluck('code')->all() === ['GH'])
                ->where('dialCodeOptions', fn ($options) => collect($options)->pluck('label')->all() === ['Ghana (+233)'])
            );
    }
}