<?php

namespace App\Http\Controllers;

use App\Support\BuyerDashboardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerOrdersController extends Controller
{
    public function __invoke(Request $request, BuyerDashboardData $dashboardData): Response
    {
        $user = $request->user();
        abort_unless($user?->isBuyer(), 403);

        $orders = $dashboardData->orders($user);
        $orderSummary = $dashboardData->orderSummary($user, $orders);
        $summary = $dashboardData->requestSummary($user);
        $reviewSummary = $dashboardData->reviewSummary($user);

        return Inertia::render('Buyer/Dashboard/Orders', [
            'dashboard' => $dashboardData->dashboard($summary, $reviewSummary, $orderSummary),
            'orders' => $orders,
        ]);
    }

    public function modal(Request $request, \App\Models\Offer $offer, BuyerDashboardData $dashboardData): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->isBuyer(), 403);

        $order = $dashboardData->order($user, $offer);

        abort_unless($order, 404);

        return response()->json([
            'order' => $order,
        ]);
    }
}
