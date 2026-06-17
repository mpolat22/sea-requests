<?php

namespace Tests\Feature;

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

    public function test_password_reset_sends_completion_email(): void
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

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $user->password));

        Notification::assertSentTo($user, PasswordResetCompletedNotification::class);
    }
}
