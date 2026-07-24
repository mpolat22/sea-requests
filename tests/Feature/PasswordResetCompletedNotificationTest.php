<?php

namespace Tests\Feature;

use App\Models\OutreachContact;
use App\Models\User;
use App\Notifications\PasswordResetCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetCompletedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_sends_completion_email_and_logs_user_in(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset-finish@example.test',
            'password' => Hash::make('OldPassword123'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'reset-finish@example.test',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertRedirect(route('buyer.requests'));
        $response->assertSessionHas('success');

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $user->password));
        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo($user, PasswordResetCompletedNotification::class);
    }

    public function test_pre_registered_supplier_is_logged_in_and_redirected_to_verification_after_password_reset(): void
    {
        Notification::fake();

        $seller = User::factory()->create([
            'role' => 'seller',
            'email' => 'supplier-onboarding@example.test',
            'email_verified_at' => now(),
            'approval_status' => 'pending',
            'password' => Hash::make('TemporaryPassword123'),
        ]);

        OutreachContact::query()->create([
            'email' => 'supplier-onboarding@example.test',
            'audience' => 'seller',
            'organization_name' => 'Supplier Onboarding Ltd',
            'source_name' => 'Bulk company import',
            'source_payload' => [
                'created_from' => 'bulk_company_import',
                'onboarding_status' => 'email_sent',
                'user_id' => $seller->id,
                'parsed' => [
                    'company_name' => 'Supplier Onboarding Ltd',
                    'email' => 'supplier-onboarding@example.test',
                ],
            ],
        ]);

        $token = Password::broker()->createToken($seller);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'supplier-onboarding@example.test',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertRedirect(route('seller.verification.create'));
        $response->assertSessionHas('success');

        $seller->refresh();
        $contact = OutreachContact::query()->where('email', 'supplier-onboarding@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('NewPassword123', $seller->password));
        $this->assertAuthenticatedAs($seller);
        $this->assertNotNull(data_get($contact->source_payload, 'account_completed_at'));

        Notification::assertSentTo($seller, PasswordResetCompletedNotification::class);
    }
}