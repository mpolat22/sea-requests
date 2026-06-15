<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BuyerDashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isBuyer(), 403);

        return redirect()->route('buyer.requests');
    }
}
