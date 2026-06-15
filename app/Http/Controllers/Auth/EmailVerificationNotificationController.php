<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\DashboardRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request, DashboardRedirector $redirector): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            $user = $request->user();

            if ($blockedRoute = $redirector->blockingRouteFor($user)) {
                return redirect()->to($blockedRoute);
            }

            $next = $request->session()->pull('auth.next');

            return redirect()->to($redirector->intendedOrHome($user, $next));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'verification-link-sent');
    }
}
