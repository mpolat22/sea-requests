<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\UserFacingMail;
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

    public function store(Request $request, UserFacingMail $mail): RedirectResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'email' => ['required', 'email:rfc', 'regex:/^[^\s@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/'],
        ], $this->messages());

        $attempt = $mail->attempt(
            fn () => Password::sendResetLink($request->only('email')),
            Password::RESET_LINK_SENT
        );

        if (! $attempt['ok']) {
            return back()->with('error', 'We could not send the password reset email right now. Please try again shortly.');
        }

        $status = $attempt['result'];

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
