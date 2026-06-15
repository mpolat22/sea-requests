<?php

namespace App\Http\Controllers;

use App\Support\BuyerDashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerRequestsController extends Controller
{
    public function __invoke(Request $request, BuyerDashboardData $dashboardData): Response
    {
        $user = $request->user();
        abort_unless($user?->isBuyer(), 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 10);
        $pageNumber = (int) ($validated['page'] ?? 1);

        $rfqsPage = $dashboardData->rfqPage($user, $search, $perPage, $pageNumber);
        $summary = $dashboardData->requestSummary($user);
        $reviewSummary = $dashboardData->reviewSummary($user);
        $orderSummary = $dashboardData->orderSummary($user);

        return Inertia::render('Buyer/Dashboard/Requests', [
            'dashboard' => $dashboardData->dashboard($summary, $reviewSummary, $orderSummary),
            'rfqsPage' => $rfqsPage,
            'createUrl' => route('rfqs.create'),
            'indexUrl' => route('buyer.requests'),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }
}
