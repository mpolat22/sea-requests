<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Support\SellerDashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerAwardDetailController extends Controller
{
    public function __invoke(Request $request, Offer $offer, SellerDashboardData $dashboardData): Response
    {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);
        abort_unless((int) $offer->seller_id === (int) $user->id, 404);

        $order = $dashboardData->order($user, $offer);

        abort_unless($order, 404);

        return Inertia::render('Supplier/Dashboard/OrderDetail', [
            'dashboard' => $dashboardData->workspace($user),
            'order' => $order,
        ]);
    }
}
