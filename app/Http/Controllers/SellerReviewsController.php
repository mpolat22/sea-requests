<?php

namespace App\Http\Controllers;

use App\Support\SellerDashboardData;
use App\Support\SupplierReviewData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerReviewsController extends Controller
{
    public function __invoke(
        Request $request,
        SellerDashboardData $dashboardData,
        SupplierReviewData $reviewData
    ): Response {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);

        $reviews = $reviewData->sellerDashboardReviews($user)
            ->map(fn (array $review) => [
                ...$review,
                'delete_reply_url' => filled($review['seller_reply'])
                    ? route('seller.reviews.reply.destroy', $review['id'])
                    : null,
            ])
            ->values();

        return Inertia::render('Supplier/Dashboard/Reviews', [
            'dashboard' => $dashboardData->profile($user),
            'reviews' => $reviews,
            'summary' => $reviewData->summaryForSeller($user),
        ]);
    }
}
