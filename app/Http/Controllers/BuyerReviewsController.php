<?php

namespace App\Http\Controllers;

use App\Support\BuyerDashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerReviewsController extends Controller
{
    public function __invoke(Request $request, BuyerDashboardData $dashboardData): Response
    {
        $user = $request->user();
        abort_unless($user?->isBuyer(), 403);

        $reviews = $dashboardData->reviews($user);
        $reviewSummary = $dashboardData->reviewSummary($user, $reviews);
        $summary = $dashboardData->requestSummary($user);
        $orderSummary = $dashboardData->orderSummary($user);

        return Inertia::render('Buyer/Dashboard/Reviews', [
            'dashboard' => $dashboardData->dashboard($summary, $reviewSummary, $orderSummary),
            'reviews' => $reviews,
        ]);
    }
}
