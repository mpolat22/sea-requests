<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_own_profile_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin User',
            'email' => 'admin@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Profile')
                ->where('profile.name', 'Admin User')
                ->where('profile.email', 'admin@example.test')
                ->where('updateUrl', route('admin.profile.password.update'))
            );
    }

    public function test_admin_can_change_password_with_current_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => 'OldPassword123',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.profile.password.update'), [
                'current_password' => 'OldPassword123',
                'password' => 'NewPassword456',
                'password_confirmation' => 'NewPassword456',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'admin-password-updated');

        $this->assertTrue(Hash::check('NewPassword456', $admin->fresh()->password));
        $this->assertFalse(Hash::check('OldPassword123', $admin->fresh()->password));
    }

    public function test_admin_cannot_change_password_with_wrong_current_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => 'OldPassword123',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.profile.password.update'), [
                'current_password' => 'WrongPassword123',
                'password' => 'NewPassword456',
                'password_confirmation' => 'NewPassword456',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword123', $admin->fresh()->password));
    }

    public function test_admin_password_must_be_strong_and_confirmed(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => 'OldPassword123',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.profile.password.update'), [
                'current_password' => 'OldPassword123',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('OldPassword123', $admin->fresh()->password));
    }

    public function test_non_admin_cannot_open_or_update_admin_profile(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);

        $this->actingAs($buyer)
            ->get(route('admin.profile.edit'))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->patch(route('admin.profile.password.update'), [
                'current_password' => 'Password123',
                'password' => 'NewPassword456',
                'password_confirmation' => 'NewPassword456',
            ])
            ->assertForbidden();
    }
}
