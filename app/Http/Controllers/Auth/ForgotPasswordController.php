<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'email' => ['required', 'email:rfc', 'regex:/^[^\s@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/'],
        ], $this->messages());

        $status = Password::sendResetLink($request->only('email'));

        return back()->with($status === Password::RESET_LINK_SENT
            ? ['success' => __($status)]
            : ['error' => __($status)]);
    }

    private function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.regex' => 'Please enter a valid email address.',
        ];
    }
}
