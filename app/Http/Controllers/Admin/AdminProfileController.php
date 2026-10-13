<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AdminProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        return Inertia::render('Admin/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'updateUrl' => route('admin.profile.password.update'),
            'backUrl' => route('admin.dashboard'),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $user->forceFill(['password' => $validated['password']])->save();

        return back()->with('success', 'admin-password-updated');
    }
}
