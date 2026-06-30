<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthEmailNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_accepts_email_with_hidden_turkish_dotted_i_variants(): void
    {
        $user = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'approved_at' => now(),
            'email' => 'admin@searequests.ai',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => "admi\u{0307}n@searequests.ai\u{0307}",
            'password' => 'Password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_normalizes_hidden_turkish_dotted_i_variants_in_email(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'account_type' => 'buyer',
            'name' => 'Buyer Demo',
            'country' => 'Turkey',
            'phone_country_code' => '+90',
            'phone' => '5550000000',
            'email' => "admi\u{0307}n-register@searequests.ai\u{0307}",
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'agree_to_terms' => true,
        ]);

        $response->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'admin-register@searequests.ai',
        ]);
    }
}
