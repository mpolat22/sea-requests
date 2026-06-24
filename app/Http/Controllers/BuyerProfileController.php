<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\UserFacingMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BuyerProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->isBuyer(), 403);

        return Inertia::render('Buyer/Dashboard/Profile', [
            'profile' => [
                'name' => $user->name ?? '',
                'phone' => $user->phone ?? '',
                'whatsapp_number' => $user->whatsapp_number ?? '',
                'country' => $user->country ?? '',
                'email' => $user->email ?? '',
                'email_verified_at' => optional($user->email_verified_at)?->toISOString(),
            ],
            'updateUrl' => route('buyer.profile.update'),
            'backUrl' => route('buyer.requests'),
        ]);
    }

    public function update(Request $request, UserFacingMail $mail): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user?->isBuyer(), 403);

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'phone' => $this->normalizePhoneNumber($request->input('phone')),
            'whatsapp_number' => $this->normalizePhoneNumber($request->input('whatsapp_number')),
            'country' => trim((string) $request->input('country')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'phone_country_code' => ['required', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'whatsapp_country_code' => [Rule::requiredIf(filled($request->input('whatsapp_number'))), 'nullable', 'string', 'regex:/^\+\d{1,4}$/'],
            'whatsapp_number' => ['nullable', 'string', 'regex:/^[0-9]{6,15}$/'],
            'email' => ['required', 'email:rfc', 'regex:/^[^\s@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', 'max:255', 'unique:users,email,'.$user->id],
        ], [
            'name.required' => 'Full Name is required.',
            'name.min' => 'Full Name must be at least 2 characters.',
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
        ], [
            'name' => 'Full Name',
            'phone' => 'Phone Number',
            'phone_country_code' => 'phone country code',
            'whatsapp_country_code' => 'WhatsApp country code',
            'whatsapp_number' => 'WhatsApp Number',
            'country' => 'Country',
            'email' => 'Email',
        ]);

        $emailChanged = $validated['email'] !== $user->email;

        $user->forceFill([
            'name' => $validated['name'],
            'phone' => trim($validated['phone_country_code'].' '.$validated['phone']),
            'country' => $validated['country'],
            'countries' => $validated['country'],
            'whatsapp_number' => filled($validated['whatsapp_number'] ?? null)
                ? trim($validated['whatsapp_country_code'].' '.$validated['whatsapp_number'])
                : null,
            'email' => $validated['email'],
            'locale' => 'en',
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged) {
            $verificationEmail = $mail->attempt(fn () => $user->sendEmailVerificationNotification());
            $redirect = redirect()
                ->route('verification.notice')
                ->with('success', 'buyer-email-updated');

            if (! $verificationEmail['ok']) {
                return $redirect->with('error', 'Your email address was updated, but we could not send the verification email right now. Please try again shortly.');
            }

            return $redirect;
        }

        return back()->with('success', 'buyer-profile-updated');
    }

    private function normalizePhoneNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }
}
