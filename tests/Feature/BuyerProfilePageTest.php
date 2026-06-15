<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BuyerProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_open_profile_page(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Buyer Demo',
            'company_name' => 'Buyer Company',
            'phone' => '+90 5412345678',
            'whatsapp_number' => '+90 5321234567',
            'country' => 'Turkey',
            'email' => 'buyer-profile@example.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Buyer/Dashboard/Profile')
                ->where('profile.name', 'Buyer Demo')
                ->where('profile.phone', '+90 5412345678')
                ->where('profile.whatsapp_number', '+90 5321234567')
                ->where('profile.country', 'Turkey')
                ->where('profile.email', 'buyer-profile@example.test')
            );
    }

    public function test_buyer_can_update_profile_without_changing_email(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'company_name' => 'Existing Buyer Company',
            'email' => 'buyer-update@example.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->patch(route('buyer.profile.update'), [
                'name' => 'Updated Buyer',
                'country' => 'Turkey',
                'phone_country_code' => '+90',
                'phone' => '5412345678',
                'whatsapp_country_code' => '+90',
                'whatsapp_number' => '5321234567',
                'email' => 'buyer-update@example.test',
            ])
            ->assertRedirect();

        $buyer->refresh();

        $this->assertSame('Updated Buyer', $buyer->name);
        $this->assertSame('Existing Buyer Company', $buyer->company_name);
        $this->assertSame('+90 5412345678', $buyer->phone);
        $this->assertSame('+90 5321234567', $buyer->whatsapp_number);
        $this->assertSame('Turkey', $buyer->country);
        $this->assertNotNull($buyer->email_verified_at);
    }

    public function test_buyer_profile_uses_register_style_required_contact_fields(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'email' => 'buyer-required@example.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->from(route('buyer.profile.edit'))
            ->patch(route('buyer.profile.update'), [
                'name' => 'Updated Buyer',
                'country' => 'Turkey',
                'phone_country_code' => '',
                'phone' => '',
                'email' => 'buyer-required@example.test',
            ])
            ->assertRedirect(route('buyer.profile.edit'))
            ->assertSessionHasErrors([
                'phone_country_code',
                'phone',
            ]);
    }

    public function test_buyer_email_change_requires_new_verification(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'email' => 'buyer-old@example.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->patch(route('buyer.profile.update'), [
                'name' => 'Updated Buyer',
                'country' => 'Turkey',
                'phone_country_code' => '+90',
                'phone' => '5412345678',
                'whatsapp_country_code' => '+90',
                'whatsapp_number' => '5321234567',
                'email' => 'buyer-new@example.test',
            ])
            ->assertRedirect(route('verification.notice'));

        $buyer->refresh();

        $this->assertSame('buyer-new@example.test', $buyer->email);
        $this->assertNull($buyer->email_verified_at);
    }
}
