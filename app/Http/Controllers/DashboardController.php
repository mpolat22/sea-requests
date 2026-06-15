<?php

namespace App\Http\Controllers;

use App\Support\DashboardRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardRedirector $redirector): RedirectResponse
    {
        $user = $request->user();

        if ($blockedRoute = $redirector->blockingRouteFor($user)) {
            return redirect()->to($blockedRoute);
        }

        $next = $request->session()->pull('auth.next');

        return redirect()->to($redirector->intendedOrHome($user, $next));
    }
}
