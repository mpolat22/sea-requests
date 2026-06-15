<?php

namespace App\Http\Controllers;

use App\Support\SellerDashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerIncomingRequestsController extends Controller
{
    public function __invoke(Request $request, SellerDashboardData $dashboardData): Response
    {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);

        return Inertia::render('Supplier/Dashboard/IncomingRequests', [
            'dashboard' => $dashboardData->workspace($user),
            'incomingRequests' => $dashboardData->incomingRequests($user),
        ]);
    }
}
