<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\UserFacingMail;
use App\Models\User;
use App\Support\MarketplaceNotificationCenter;
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
        ]);
    }

    public function store(Request $request, UserFacingMail $mail): RedirectResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
            'phone' => $this->normalizePhoneNumber($request->input('phone')),
            'whatsapp_number' => $this->normalizePhoneNumber($request->input('whatsapp_number')),
            'name' => trim((string) $request->input('name')),
            'company_name' => trim((string) $request->input('company_name')),
            'company_description' => trim((string) $request->input('company_description')),
        ]);

        $validated = $request->validate([
            'account_type' => ['required', 'in:buyer,seller'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'company_name' => [Rule::requiredIf($request->input('account_type') === 'seller'), 'nullable', 'string', 'min:2', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'phone_country_code' => ['required', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'whatsapp_country_code' => [Rule::requiredIf(filled($request->input('whatsapp_number'))), 'nullable', 'string', 'regex:/^\+\d{1,4}$/'],
            'whatsapp_number' => ['nullable', 'string', 'regex:/^[0-9]{6,15}$/'],
            'company_description' => ['nullable', 'string', 'max:2000'],
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
            'company_name' => $validated['company_name'] ?? null,
            'phone' => trim($validated['phone_country_code'].' '.$validated['phone']),
            'country' => $validated['country'],
            'countries' => $validated['country'],
            'whatsapp_number' => filled($validated['whatsapp_number'] ?? null)
                ? trim($validated['whatsapp_country_code'].' '.$validated['whatsapp_number'])
                : null,
            'company_description' => $validated['company_description'] ?? null,
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
            return $redirect->with('error', 'Your account was created, but we could not send the verification email right now. Please use Resend Verification Email to try again shortly.');
        }

        return $redirect;
    }

    private function normalizePhoneNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }

    private function normalizeEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $email = strtolower(trim($value));

        return $email === '' ? null : $email;
    }

    private function validationMessages(): array
    {
        return [
            'name.required' => 'Full Name is required.',
            'name.min' => 'Full Name must be at least 2 characters.',
            'company_name.required' => 'Company Name is required.',
            'company_name.min' => 'Company Name must be at least 2 characters.',
            'country.required' => 'Country is required.',
            'phone_country_code.required' => 'Please select a country code.',
            'phone_country_code.regex' => 'Please select a valid country code.',
            'phone.required' => 'Phone Number is required.',
            'phone.regex' => 'Phone Number must be between 6 and 15 digits.',
            'whatsapp_country_code.required' => 'Please select a country code.',
            'whatsapp_country_code.regex' => 'Please select a valid country code.',
            'whatsapp_number.regex' => 'WhatsApp Number must be between 6 and 15 digits.',
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
            'phone_country_code' => 'phone country code',
            'phone' => 'Phone Number',
            'whatsapp_country_code' => 'WhatsApp country code',
            'whatsapp_number' => 'WhatsApp Number',
            'company_description' => 'Company Description',
            'email' => 'Email',
            'password' => 'Password',
            'agree_to_terms' => 'terms',
        ];
    }
}
