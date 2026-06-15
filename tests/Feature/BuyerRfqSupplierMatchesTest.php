<?php

namespace Tests\Feature;

use App\Models\Port;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuyerRfqSupplierMatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_matches_return_empty_when_no_ports_are_selected(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $albaniaPort = $this->createActivePort();
        $this->createSupplierListingForPorts([$albaniaPort]);

        $this->actingAs($buyer)
            ->getJson(route('rfqs.supplier-matches', [
                'country_names' => [$albaniaPort->country_name],
                'port_ids' => [],
            ]))
            ->assertOk()
            ->assertJson([
                'suppliers' => [],
                'summary' => [
                    'count' => 0,
                ],
            ]);
    }

    public function test_supplier_matches_require_ports_for_every_selected_country(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
        ]);

        $albaniaPort = $this->createActivePort(
            countryName: 'Albania',
            countryCode: 'AL',
            portName: 'Durres',
            locationCode: 'DRZ',
            unlocode: 'ALDRZ',
        );
        $anguillaPort = $this->createActivePort(
            countryName: 'Anguilla',
            countryCode: 'AI',
            portName: 'Blowing Point',
            locationCode: 'BLP',
            unlocode: 'AIBLP',
        );

        $listing = $this->createSupplierListingForPorts([$albaniaPort, $anguillaPort]);

        $this->actingAs($buyer)
            ->getJson(route('rfqs.supplier-matches', [
                'country_names' => [$albaniaPort->country_name, $anguillaPort->country_name],
                'port_ids' => [$albaniaPort->id],
            ]))
            ->assertOk()
            ->assertJson([
                'suppliers' => [],
                'summary' => [
                    'count' => 0,
                ],
            ]);

        $this->actingAs($buyer)
            ->getJson(route('rfqs.supplier-matches', [
                'country_names' => [$albaniaPort->country_name, $anguillaPort->country_name],
                'port_ids' => [$albaniaPort->id, $anguillaPort->id],
            ]))
            ->assertOk()
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('suppliers.0.id', $listing->id);
    }

    private function createActivePort(
        string $countryName = 'Albania',
        string $countryCode = 'AL',
        string $portName = 'Durres',
        string $locationCode = 'DRZ',
        string $unlocode = 'ALDRZ',
    ): Port {
        return Port::query()->create([
            'unlocode' => $unlocode,
            'country_code' => $countryCode,
            'location_code' => $locationCode,
            'country_name' => $countryName,
            'port_name' => $portName,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, Port>  $ports
     */
    private function createSupplierListingForPorts(array $ports): SupplierServiceListing
    {
        $primaryPort = $ports[0];

        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'Atlas Marine Service',
            'country' => $primaryPort->country_name,
            'service_country_codes' => collect($ports)->pluck('country_code')->unique()->values()->all(),
        ]);

        $seller->servicePorts()->attach(collect($ports)->pluck('id')->all());

        $listing = SupplierServiceListing::query()->create([
            'seller_id' => $seller->id,
            'listing_key' => 'atlas-service-'.Str::uuid(),
            'company_name' => $seller->company_name,
            'contact_name' => $seller->name,
            'country' => $primaryPort->country_name,
            'summary' => 'Rapid onboard service attendance.',
            'vendor_slug' => Str::slug($seller->company_name),
            'search_text' => collect($ports)
                ->map(fn (Port $port) => "{$seller->company_name} {$port->country_name} {$port->port_name}")
                ->implode(' '),
            'is_visible' => true,
        ]);

        foreach ($ports as $port) {
            $listing->ports()->create([
                'country_code' => $port->country_code,
                'country_name' => $port->country_name,
                'port_name' => $port->port_name,
                'unlocode' => $port->unlocode,
            ]);
        }

        return $listing;
    }
}
