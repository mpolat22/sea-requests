<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\SupplierReview;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SupplierReviewData
{
    public function summaryForSeller(User $seller): array
    {
        return Cache::remember($this->summaryCacheKey($seller->id), now()->addMinutes(10), function () use ($seller): array {
            $aggregate = SupplierReview::query()
                ->where('seller_id', $seller->id)
                ->selectRaw('COUNT(*) as reviews_count, AVG(rating) as average_rating')
                ->first();

            $count = (int) ($aggregate?->reviews_count ?? 0);
            $average = $count > 0 ? round((float) ($aggregate?->average_rating ?? 0), 1) : null;

            return [
                'count' => $count,
                'average' => $average,
            ];
        });
    }

    public function publicReviewsForSeller(User $seller, bool $maskBuyerCompany = true): Collection
    {
        $items = Cache::remember($this->publicReviewsCacheKey($seller->id, $maskBuyerCompany), now()->addMinutes(10), function () use ($seller, $maskBuyerCompany): array {
            return SupplierReview::query()
                ->where('seller_id', $seller->id)
                ->with([
                    'buyer:id,name,company_name',
                    'rfq:id,reference_no,ship_name',
                ])
                ->latest()
                ->get()
                ->map(fn (SupplierReview $review) => $this->mapPublicReview($review, $maskBuyerCompany))
                ->values()
                ->all();
        });

        return collect($items);
    }

    public function forgetSellerReviewCaches(int $sellerId): void
    {
        Cache::forget($this->summaryCacheKey($sellerId));
        Cache::forget($this->publicReviewsCacheKey($sellerId, true));
        Cache::forget($this->publicReviewsCacheKey($sellerId, false));
    }

    public function reviewTargetsForBuyer(User $buyer, User $seller): Collection
    {
        return Offer::query()
            ->where('seller_id', $seller->id)
            ->whereHas('awards', fn ($query) => $query
                ->where('buyer_id', $buyer->id)
                ->where('status', OfferAward::STATUS_CONFIRMED))
            ->with([
                'rfq:id,reference_no,service_title',
                'review',
                'awards' => fn ($query) => $query
                    ->where('buyer_id', $buyer->id)
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->orderByDesc('confirmed_at'),
            ])
            ->latest('updated_at')
            ->get()
            ->map(function (Offer $offer) {
                $review = $offer->review;
                $latestAward = $offer->awards->first();

                return [
                    'offer_id' => $offer->id,
                    'reference_no' => $offer->rfq?->reference_no,
                    'service_title' => $offer->rfq?->service_title,
                    'confirmed_at' => optional($latestAward?->confirmed_at)->toISOString(),
                    'status_label' => $review ? 'Review saved' : 'Review pending',
                    'review' => $review ? [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'review_text' => $review->review_text,
                        'seller_reply' => $review->seller_reply,
                        'seller_replied_at' => optional($review->seller_replied_at)->toISOString(),
                    ] : null,
                ];
            })
            ->values();
    }

    public function buyerDashboardReviews(User $buyer): Collection
    {
        return Offer::query()
            ->whereHas('awards', fn ($query) => $query
                ->where('buyer_id', $buyer->id)
                ->where('status', OfferAward::STATUS_CONFIRMED))
            ->with([
                'seller:id,name,company_name,service_category_ids,service_subcategory_ids',
                'rfq:id,reference_no,ship_name',
                'review',
                'awards' => fn ($query) => $query
                    ->where('buyer_id', $buyer->id)
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->orderByDesc('confirmed_at'),
            ])
            ->latest('updated_at')
            ->get()
            ->map(function (Offer $offer) {
                $review = $offer->review;
                $latestAward = $offer->awards->first();
                $seller = $offer->seller;

                return [
                    'offer_id' => $offer->id,
                    'review_id' => $review?->id,
                    'reference_no' => $offer->rfq?->reference_no,
                    'ship_name' => $offer->rfq?->ship_name,
                    'supplier_name' => $seller?->company_name ?: $seller?->name,
                    'confirmed_at' => optional($latestAward?->confirmed_at)->toISOString(),
                    'status' => $review ? 'published' : 'pending',
                    'rating' => $review?->rating,
                    'review_text' => $review?->review_text,
                    'seller_reply' => $review?->seller_reply,
                    'seller_replied_at' => optional($review?->seller_replied_at)->toISOString(),
                    'service_url' => $seller ? ServiceRoute::firstProfileUrl($seller, [
                        'tab' => 'reviews',
                        'review_offer' => $offer->id,
                    ]) : route('services.index'),
                ];
            })
            ->values();
    }

    public function sellerDashboardReviews(User $seller): Collection
    {
        return SupplierReview::query()
            ->where('seller_id', $seller->id)
            ->with([
                'buyer:id,name,company_name',
                'rfq:id,reference_no,ship_name',
            ])
            ->latest()
            ->get()
            ->map(function (SupplierReview $review) use ($seller) {
                return [
                    'id' => $review->id,
                    'reference_no' => $review->rfq?->reference_no,
                    'ship_name' => $review->rfq?->ship_name,
                    'buyer_company' => $review->buyer?->company_name ?: $review->buyer?->name,
                    'rating' => $review->rating,
                    'review_text' => $review->review_text,
                    'seller_reply' => $review->seller_reply,
                    'seller_replied_at' => optional($review->seller_replied_at)->toISOString(),
                    'created_at' => optional($review->created_at)->toISOString(),
                    'service_url' => ServiceRoute::firstProfileUrl($seller, [
                        'tab' => 'reviews',
                    ]),
                ];
            })
            ->values();
    }

    public function canBuyerReviewOffer(User $buyer, Offer $offer): bool
    {
        if ((int) $offer->seller_id <= 0) {
            return false;
        }

        return $offer->awards()
            ->where('buyer_id', $buyer->id)
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->exists();
    }

    protected function mapPublicReview(SupplierReview $review, bool $maskBuyerCompany = true): array
    {
        $buyerCompany = $review->buyer?->company_name ?: $review->buyer?->name;

        return [
            'id' => $review->id,
            'offer_id' => $review->offer_id,
            'buyer_company_full' => trim((string) $buyerCompany),
            'buyer_company' => $maskBuyerCompany ? $this->maskCompanyName($buyerCompany) : trim((string) $buyerCompany ?: '-'),
            'reference_no' => $review->rfq?->reference_no,
            'ship_name' => $review->rfq?->ship_name,
            'rating' => $review->rating,
            'review_text' => $review->review_text,
            'seller_reply' => $review->seller_reply,
            'seller_replied_at' => optional($review->seller_replied_at)->toISOString(),
            'created_at' => optional($review->created_at)->toISOString(),
        ];
    }

    protected function maskCompanyName(?string $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '-';
        }

        if (mb_strlen($text) <= 3) {
            return $text.'***';
        }

        return mb_substr($text, 0, 3).'***';
    }

    protected function summaryCacheKey(int $sellerId): string
    {
        return "supplier_review_summary_v1_{$sellerId}";
    }

    protected function publicReviewsCacheKey(int $sellerId, bool $maskBuyerCompany): string
    {
        return 'supplier_public_reviews_v1_'.$sellerId.'_'.($maskBuyerCompany ? 'masked' : 'full');
    }
}
