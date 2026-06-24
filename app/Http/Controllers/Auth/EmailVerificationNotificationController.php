<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\DashboardRedirector;
use App\Support\UserFacingMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request, DashboardRedirector $redirector, UserFacingMail $mail): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            $user = $request->user();

            if ($blockedRoute = $redirector->blockingRouteFor($user)) {
                return redirect()->to($blockedRoute);
            }

            $next = $request->session()->pull('auth.next');

            return redirect()->to($redirector->intendedOrHome($user, $next));
        }

        $attempt = $mail->attempt(fn () => $request->user()->sendEmailVerificationNotification());

        if (! $attempt['ok']) {
            return back()->with('error', 'We could not send the verification email right now. Please try again shortly.');
        }

        return back()->with('success', 'verification-link-sent');
    }
}
