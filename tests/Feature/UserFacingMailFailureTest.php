<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserFacingMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class UserFacingMailFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_shows_friendly_error_when_verification_email_cannot_be_sent(): void
    {
        $this->swapUserFacingMail(false);

        $response = $this->post('/register', [
            'account_type' => 'buyer',
            'name' => 'Mustafa Polat',
            'country' => 'Turkey',
            'phone_country_code' => '+90',
            'phone' => '5413342219',
            'whatsapp_country_code' => '+90',
            'whatsapp_number' => '5413342219',
            'company_description' => 'Buyer profile',
            'email' => 'register-mail-failure@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'agree_to_terms' => '1',
        ]);

        $user = User::query()->where('email', 'register-mail-failure@example.test')->first();

        $this->assertNotNull($user);

        $response
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error', 'Your account was created, but we could not send the verification email right now. Please use Resend Verification Email to try again shortly.');

        $this->assertAuthenticatedAs($user);
    }

    public function test_forgot_password_shows_friendly_error_when_reset_email_cannot_be_sent(): void
    {
        $this->swapUserFacingMail(false);

        User::factory()->create([
            'email' => 'forgot-mail-failure@example.test',
        ]);

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'forgot-mail-failure@example.test',
        ]);

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error', 'We could not send the password reset email right now. Please try again shortly.');
    }

    public function test_resend_verification_shows_friendly_error_when_mail_cannot_be_sent(): void
    {
        $this->swapUserFacingMail(false);

        $user = User::factory()->unverified()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'));

        $response
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error', 'We could not send the verification email right now. Please try again shortly.');
    }

    public function test_buyer_profile_email_change_shows_friendly_error_when_verification_mail_cannot_be_sent(): void
    {
        $this->swapUserFacingMail(false);

        $buyer = User::factory()->create([
            'role' => 'buyer',
            'email' => 'buyer-old@example.test',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($buyer)
            ->patch(route('buyer.profile.update'), [
                'name' => 'Updated Buyer',
                'country' => 'Turkey',
                'phone_country_code' => '+90',
                'phone' => '5413342219',
                'whatsapp_country_code' => '+90',
                'whatsapp_number' => '5413342219',
                'email' => 'buyer-new@example.test',
            ]);

        $response
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success', 'buyer-email-updated')
            ->assertSessionHas('error', 'Your email address was updated, but we could not send the verification email right now. Please try again shortly.');

        $buyer->refresh();

        $this->assertSame('buyer-new@example.test', $buyer->email);
        $this->assertNull($buyer->email_verified_at);
    }

    public function test_password_reset_still_succeeds_when_confirmation_mail_cannot_be_sent(): void
    {
        $this->swapUserFacingMail(false);

        $user = User::factory()->create([
            'email' => 'reset-mail-failure@example.test',
            'password' => Hash::make('OldPassword123'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'reset-mail-failure@example.test',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('success')
            ->assertSessionHas('error', 'Your password was reset, but we could not send the confirmation email right now.');

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $user->password));
    }

    private function swapUserFacingMail(bool $ok): void
    {
        $this->app->instance(UserFacingMail::class, new class($ok) extends UserFacingMail
        {
            public function __construct(private readonly bool $ok)
            {
            }

            public function attempt(callable $callback, mixed $fallbackResult = null): array
            {
                if ($this->ok) {
                    return parent::attempt($callback, $fallbackResult);
                }

                return [
                    'ok' => false,
                    'result' => $fallbackResult,
                ];
            }
        });
    }
}
