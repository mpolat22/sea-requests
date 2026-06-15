<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\SupplierReview;
use App\Support\MarketplaceNotificationCenter;
use App\Support\SupplierReviewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierReviewController extends Controller
{
    public function store(Request $request, SupplierReviewData $reviewData): RedirectResponse
    {
        $buyer = $request->user();
        abort_unless($buyer?->isBuyer(), 403);

        $data = $request->validate([
            'offer_id' => ['required', 'integer', 'exists:offers,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['required', 'string', 'max:2000'],
        ]);

        $offer = Offer::query()
            ->with(['rfq', 'review', 'seller'])
            ->findOrFail($data['offer_id']);

        abort_unless($reviewData->canBuyerReviewOffer($buyer, $offer), 403);

        $review = $offer->review;

        if ($review && (int) $review->buyer_id !== (int) $buyer->id) {
            abort(403);
        }

        if ($review) {
            $review->forceFill([
                'rating' => $data['rating'],
                'review_text' => trim($data['review_text']),
                'seller_reply' => null,
                'seller_replied_at' => null,
            ])->save();
        } else {
            $review = SupplierReview::query()->create([
                'offer_id' => $offer->id,
                'rfq_id' => $offer->rfq_id,
                'buyer_id' => $buyer->id,
                'seller_id' => $offer->seller_id,
                'rating' => $data['rating'],
                'review_text' => trim($data['review_text']),
            ]);

            if ($offer->seller) {
                MarketplaceNotificationCenter::notifySupplierReviewReceived(
                    $offer->seller,
                    $buyer,
                    $offer,
                    (int) $review->rating
                );
            }
        }

        $reviewData->forgetSellerReviewCaches((int) $offer->seller_id);

        return back()->with('success', 'Supplier review saved.');
    }

    public function reply(Request $request, SupplierReview $review, SupplierReviewData $reviewData): RedirectResponse
    {
        $seller = $request->user();
        abort_unless($seller?->isSeller(), 403);
        abort_unless((int) $review->seller_id === (int) $seller->id, 403);

        $data = $request->validate([
            'seller_reply' => ['required', 'string', 'max:2000'],
        ]);

        $wasEmptyReply = blank($review->seller_reply);

        $review->forceFill([
            'seller_reply' => trim($data['seller_reply']),
            'seller_replied_at' => now(),
        ])->save();

        $reviewData->forgetSellerReviewCaches((int) $review->seller_id);

        if ($wasEmptyReply) {
            $review->loadMissing(['buyer', 'seller', 'rfq']);

            if ($review->buyer && $review->seller) {
                MarketplaceNotificationCenter::notifyBuyerReviewReplyReceived(
                    $review->buyer,
                    $review->seller,
                    $review
                );
            }
        }

        return back()->with('success', 'Review reply saved.');
    }

    public function destroy(Request $request, SupplierReview $review, SupplierReviewData $reviewData): RedirectResponse
    {
        $buyer = $request->user();
        abort_unless($buyer?->isBuyer(), 403);
        abort_unless((int) $review->buyer_id === (int) $buyer->id, 403);

        $reviewData->forgetSellerReviewCaches((int) $review->seller_id);
        $review->delete();

        return back()->with('success', 'Supplier review deleted.');
    }

    public function destroyReply(Request $request, SupplierReview $review, SupplierReviewData $reviewData): RedirectResponse
    {
        $seller = $request->user();
        abort_unless($seller?->isSeller(), 403);
        abort_unless((int) $review->seller_id === (int) $seller->id, 403);

        $review->forceFill([
            'seller_reply' => null,
            'seller_replied_at' => null,
        ])->save();

        $reviewData->forgetSellerReviewCaches((int) $review->seller_id);

        return back()->with('success', 'Review reply deleted.');
    }
}
