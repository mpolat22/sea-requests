<?php

namespace App\Http\Controllers;

use App\Support\SellerDashboardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerAwardedRequestsController extends Controller
{
    public function __invoke(Request $request, SellerDashboardData $dashboardData): Response
    {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);

        return Inertia::render('Supplier/Dashboard/Orders', [
            'dashboard' => $dashboardData->workspace($user),
            'orders' => $dashboardData->orders($user),
        ]);
    }

    public function modal(Request $request, \App\Models\Offer $offer, SellerDashboardData $dashboardData): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);

        $order = $dashboardData->order($user, $offer);

        abort_unless($order, 404);

        return response()->json([
            'order' => $order,
        ]);
    }
}
