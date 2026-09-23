<?php

namespace Tests\Feature;

use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SupplierRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_registers_without_company_contact_fields_and_continues_to_verification(): void
    {
        Notification::fake();
        $this->seedCountry();

        $response = $this->post(route('register'), $this->registrationData('seller'));

        $response->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));

        $seller = User::query()->where('email', 'supplier@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($seller);
        $this->assertSame('Example Marine', $seller->company_name);
        $this->assertNull($seller->phone);
        $this->assertNull($seller->whatsapp_number);
        $this->assertNull($seller->company_description);
        $this->assertFalse($seller->hasSubmittedSellerVerification());

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $seller->id,
            'hash' => sha1($seller->getEmailForVerification()),
        ]);

        $this->get($verificationUrl)->assertRedirect(route('seller.verification.create'));
        $this->assertNotNull($seller->fresh()->email_verified_at);
    }

    public function test_supplier_registration_ignores_contact_fields_sent_outside_the_form(): void
    {
        Notification::fake();
        $this->seedCountry();

        $response = $this->post(route('register'), array_merge($this->registrationData('seller'), [
            'phone_country_code' => '+90',
            'phone' => '5550000000',
            'whatsapp_country_code' => '+90',
            'whatsapp_number' => '5550000001',
            'company_description' => 'Complete profile',
        ]));

        $response->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));

        $seller = User::query()->where('email', 'supplier@example.test')->firstOrFail();
        $this->assertNull($seller->phone);
        $this->assertNull($seller->whatsapp_number);
        $this->assertNull($seller->company_description);
    }

    public function test_buyer_registration_still_requires_phone_and_saves_contact_fields(): void
    {
        Notification::fake();
        $this->seedCountry();

        $buyerData = $this->registrationData('buyer');
        $buyerData['email'] = 'buyer@example.test';

        $this->post(route('register'), $buyerData)
            ->assertSessionHasErrors(['phone_country_code', 'phone']);

        $buyerData['phone_country_code'] = '+90';
        $buyerData['phone'] = '5550000000';
        $buyerData['whatsapp_country_code'] = '+90';
        $buyerData['whatsapp_number'] = '5550000001';

        $this->post(route('register'), $buyerData)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('verification.notice'));

        $buyer = User::query()->where('email', 'buyer@example.test')->firstOrFail();
        $this->assertSame('+90 5550000000', $buyer->phone);
        $this->assertSame('+90 5550000001', $buyer->whatsapp_number);
    }

    private function registrationData(string $role): array
    {
        return [
            'account_type' => $role,
            'name' => 'Example User',
            'company_name' => 'Example Marine',
            'country' => 'United Arab Emirates',
            'email' => 'supplier@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'agree_to_terms' => true,
        ];
    }

    private function seedCountry(): void
    {
        Port::query()->firstOrCreate(['unlocode' => 'AHDXB'], [
            'country_code' => 'AE',
            'location_code' => 'DXB',
            'country_name' => 'United Arab Emirates',
            'port_name' => 'Dubai',
            'is_active' => true,
        ]);
    }
}
