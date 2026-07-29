<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OutreachContact;
use App\Models\User;
use App\Notifications\PasswordResetCompletedNotification;
use App\Support\DashboardRedirector;
use App\Support\UserFacingMail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class ResetPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->query('email', ''),
            'token' => $request->route('token'),
        ]);
    }

    public function store(Request $request, UserFacingMail $mail, DashboardRedirector $redirector): RedirectResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'regex:/^[^\s@]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], $this->messages());

        $confirmationEmailSent = true;
        $resetUser = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($mail, &$confirmationEmailSent, &$resetUser) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                $resetUser = $user->refresh();
                $confirmationEmailSent = $mail->attempt(
                    fn () => $user->notify(new PasswordResetCompletedNotification())
                )['ok'];
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        if (! $resetUser) {
            return redirect()->route('login')->with('success', 'password-reset-success');
        }

        $this->markPreRegisteredAccountCompleted($resetUser->email);

        Auth::login($resetUser);
        $request->session()->regenerate();

        $target = $redirector->blockingRouteFor($resetUser) ?: $redirector->intendedOrHome($resetUser);
        $redirect = redirect()->to($target)->with('success', 'password-reset-success');

        if (! $confirmationEmailSent) {
            return $redirect->with('error', 'password-reset-confirmation-email-failed');
        }

        return $redirect;
    }


    private function markPreRegisteredAccountCompleted(string $email): void
    {
        $contact = OutreachContact::query()
            ->where('email', strtolower(trim($email)))
            ->whereIn('audience', ['seller', 'buyer'])
            ->whereNotNull('source_payload->onboarding_status')
            ->first();

        if (! $contact) {
            return;
        }

        $payload = $contact->source_payload ?? [];

        $contact->forceFill([
            'source_payload' => array_merge($payload, [
                'account_completed_at' => $payload['account_completed_at'] ?? now()->toIso8601String(),
            ]),
        ])->save();
    }
    private function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.regex' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation must match.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.letters' => 'Password must include at least 1 letter.',
            'password.numbers' => 'Password must include at least 1 number.',
        ];
    }
}
