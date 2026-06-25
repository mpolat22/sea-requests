<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\DashboardRedirector;
use App\Support\SellerVerificationReminderService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(
        EmailVerificationRequest $request,
        DashboardRedirector $redirector,
        SellerVerificationReminderService $sellerVerificationReminders
    ): RedirectResponse
    {
        $request->fulfill();

        $user = $request->user();

        if ($user?->isSeller()) {
            $sellerVerificationReminders->sendOnboardingIfNeeded($user);
        }

        if ($blockedRoute = $redirector->blockingRouteFor($user)) {
            return redirect()->to($blockedRoute)->with('success', 'email-verified');
        }

        $next = $request->session()->pull('auth.next');

        return redirect()->to($redirector->intendedOrHome($user, $next))
            ->with('success', 'email-verified');
    }
}
