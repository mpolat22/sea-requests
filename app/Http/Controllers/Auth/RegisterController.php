<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthCountryCatalog;
use App\Support\EmailInputNormalizer;
use App\Support\MarketplaceNotificationCenter;
use App\Support\UserFacingMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'next' => $request->query('next'),
            'role' => in_array($request->query('role'), ['buyer', 'seller'], true)
                ? $request->query('role')
                : null,
            'countryOptions' => AuthCountryCatalog::countryOptions(),
        ]);
    }

    public function store(Request $request, UserFacingMail $mail): RedirectResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
            'name' => trim((string) $request->input('name')),
            'company_name' => trim((string) $request->input('company_name')),
        ]);

        $allowedCountries = AuthCountryCatalog::countryNames();

        $validated = $request->validate([
            'account_type' => ['required', 'in:buyer,seller'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'country' => ['required', 'string', 'max:255', Rule::in($allowedCountries)],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'regex:/^[^\s@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'agree_to_terms' => ['accepted'],
            'next' => ['nullable', 'string', 'max:255'],
        ], $this->validationMessages(), $this->validationAttributes());

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'locale' => 'en',
            'password' => $validated['password'],
            'role' => $validated['account_type'],
            'company_name' => $validated['company_name'],
            'country' => $validated['country'],
            'countries' => $validated['country'],
            'approval_status' => $validated['account_type'] === 'seller' ? 'pending' : 'approved',
            'approved_at' => $validated['account_type'] === 'seller' ? null : now(),
        ]);

        Auth::login($user);

        if (filled($validated['next'] ?? null) && str_starts_with($validated['next'], '/')) {
            $request->session()->put('auth.next', $validated['next']);
        }

        $verificationEmail = $mail->attempt(fn () => $user->sendEmailVerificationNotification());
        MarketplaceNotificationCenter::notifyRegistrationCreated($user);

        $redirect = redirect()->route('verification.notice');

        if (! $verificationEmail['ok']) {
            return $redirect->with('error', 'registration-verification-email-failed');
        }

        return $redirect;
    }

    private function normalizeEmail(?string $value): ?string
    {
        return EmailInputNormalizer::normalize($value);
    }

    private function validationMessages(): array
    {
        return [
            'name.required' => 'Full Name is required.',
            'name.min' => 'Full Name must be at least 2 characters.',
            'company_name.required' => 'Company Name is required.',
            'company_name.min' => 'Company Name must be at least 2 characters.',
            'country.required' => 'Country is required.',
            'country.in' => 'Please select a valid country.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.regex' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation must match.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.letters' => 'Password must include at least 1 letter.',
            'password.numbers' => 'Password must include at least 1 number.',
            'agree_to_terms.accepted' => 'You must accept the terms to continue.',
        ];
    }

    private function validationAttributes(): array
    {
        return [
            'name' => 'Full Name',
            'company_name' => 'Company Name',
            'country' => 'Country',
            'email' => 'Email',
            'password' => 'Password',
            'agree_to_terms' => 'terms',
        ];
    }
}
