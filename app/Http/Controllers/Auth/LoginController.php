<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\DashboardRedirector;
use App\Support\EmailInputNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'next' => $request->query('next'),
        ]);
    }

    public function store(Request $request, DashboardRedirector $redirector): RedirectResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email:rfc', 'regex:/^[^\s@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'next' => ['nullable', 'string', 'max:255'],
        ], [
            'email.email' => 'Please enter a valid email address.',
            'email.regex' => 'Please enter a valid email address.',
        ]);

        if (! Auth::attempt($request->only('email', 'password'), (bool) $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $next = filled($credentials['next'] ?? null) && str_starts_with($credentials['next'], '/')
            ? $credentials['next']
            : null;

        if ($blockedRoute = $redirector->blockingRouteFor($user)) {
            if ($next !== null) {
                $request->session()->put('auth.next', $next);
            }

            return redirect()->to($blockedRoute);
        }

        return redirect()->to($redirector->intendedOrHome($user, $next));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function normalizeEmail(?string $value): ?string
    {
        return EmailInputNormalizer::normalize($value);
    }
}
