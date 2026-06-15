<?php

namespace App\Support;

use App\Models\Rfq;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RfqAccessService
{
    public function applyDirectoryVisibility(Builder $query, ?User $user): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        return $query->where(function ($visibilityQuery) use ($user) {
            $visibilityQuery->publicMarketplace();

            if ($user?->isBuyer()) {
                $visibilityQuery->orWhere(function ($privateQuery) use ($user) {
                    $privateQuery
                        ->where('visibility_scope', Rfq::VISIBILITY_PRIVATE_SUPPLIER)
                        ->where('buyer_id', $user->id);
                });
            }

            if ($user?->isSeller()) {
                $visibilityQuery->orWhere(function ($privateQuery) use ($user) {
                    $privateQuery
                        ->where('visibility_scope', Rfq::VISIBILITY_PRIVATE_SUPPLIER)
                        ->whereHas('supplierRecipients', function ($recipientQuery) use ($user) {
                            $recipientQuery->where('seller_id', $user->id);
                        });
                });
            }
        });
    }

    public function canView(Rfq $rfq, ?User $user): bool
    {
        if ($rfq->isPublicMarketplace()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isBuyerOwner($rfq, $user)) {
            return true;
        }

        return $this->isSellerRecipient($rfq, $user);
    }

    public function offerAccess(Rfq $rfq, ?User $user): array
    {
        if (! $rfq->canReceiveSupplierResponses()) {
            return [
                'state' => 'closed',
                'notice' => 'rfq_closed',
                'url' => null,
            ];
        }

        if (! $user) {
            return [
                'state' => 'login',
                'notice' => 'login_required',
                'url' => route('login'),
            ];
        }

        if ($user->isBuyer()) {
            return [
                'state' => 'blocked',
                'notice' => 'buyer_cannot_offer',
                'url' => null,
            ];
        }

        if (! $user->isSeller()) {
            return [
                'state' => 'blocked',
                'notice' => 'seller_only',
                'url' => null,
            ];
        }

        if (! $user->isApproved()) {
            return [
                'state' => 'blocked',
                'notice' => 'approval_required',
                'url' => null,
            ];
        }

        if ($rfq->isPrivateSupplierRequest() && ! $this->isSellerRecipient($rfq, $user)) {
            return [
                'state' => 'blocked',
                'notice' => 'scope_mismatch',
                'url' => null,
            ];
        }

        if (
            $rfq->isPublicMarketplace()
            && ! $this->isSellerRecipient($rfq, $user)
            && $this->matchingPublicListingsForSeller($rfq, $user)->isEmpty()
        ) {
            return [
                'state' => 'blocked',
                'notice' => 'scope_mismatch',
                'url' => null,
            ];
        }

        return [
            'state' => 'eligible',
            'notice' => null,
            'url' => route('seller.offers.create', $rfq),
        ];
    }

    public function visibilityPresentation(Rfq $rfq, ?User $viewer = null): array
    {
        if (! $rfq->isPrivateSupplierRequest()) {
            return [
                'scope' => $rfq->visibilityScope(),
                'is_private' => false,
                'badge' => null,
                'eyebrow' => 'Published Request',
                'index_note' => null,
                'detail_text' => 'Review the request scope exactly as it was published and check whether you can submit an offer from this page.',
                'detail_notice' => null,
            ];
        }

        $isBuyerOwner = $this->isBuyerOwner($rfq, $viewer);
        $isSellerRecipient = $this->isSellerRecipient($rfq, $viewer);

        $detailText = 'This request was sent directly to the selected supplier and is not listed publicly.';
        $detailNotice = 'Only the buyer, the selected supplier, and admin can view this request.';

        if ($isSellerRecipient) {
            $detailText = 'This request was sent directly to your company and is not listed publicly.';
            $detailNotice = 'Only your company, the buyer, and admin can view this request.';
        } elseif ($viewer?->isAdmin()) {
            $detailText = 'This request was sent directly to a selected supplier and is not listed publicly.';
            $detailNotice = 'Only the buyer, the selected supplier, and admin can view this request.';
        } elseif ($isBuyerOwner) {
            $detailText = 'This request was sent directly to the selected supplier and is not listed publicly.';
            $detailNotice = 'Only you, the selected supplier, and admin can view this request.';
        }

        return [
            'scope' => $rfq->visibilityScope(),
            'is_private' => true,
            'badge' => 'Private Request',
            'eyebrow' => 'Private Request',
            'index_note' => 'Visible only to the buyer, the selected supplier, and admin.',
            'detail_text' => $detailText,
            'detail_notice' => $detailNotice,
        ];
    }

    public function isBuyerOwner(Rfq $rfq, ?User $user): bool
    {
        return (bool) ($user?->isBuyer() && (int) $rfq->buyer_id === (int) $user->id);
    }

    public function isSellerRecipient(Rfq $rfq, ?User $user): bool
    {
        if (! $user?->isSeller()) {
            return false;
        }

        return $this->recipientSellerIds($rfq)->contains((int) $user->id);
    }

    public function recipientSellerIds(Rfq $rfq): Collection
    {
        return $rfq->relationLoaded('supplierRecipients')
            ? $rfq->supplierRecipients
                ->pluck('seller_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : $rfq->supplierRecipients()
                ->pluck('seller_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
    }

    public function matchingPublicListingsForSeller(Rfq $rfq, User $user): Collection
    {
        return $this->matchingPublicListingsForSellerFromPool(
            $rfq,
            $user,
            $this->visibleSellerListingsForUser($user)
        );
    }

    public function firstMatchingPublicListingForSeller(Rfq $rfq, User $user): ?SupplierServiceListing
    {
        return $this->matchingPublicListingsForSeller($rfq, $user)->first();
    }

    public function publicIncomingRequestsForSeller(User $user): Collection
    {
        if (! $user->isSeller() || ! $user->isApproved()) {
            return collect();
        }

        $sellerListings = $this->visibleSellerListingsForUser($user);
        $rfqColumns = [
            'id',
            'reference_no',
            'request_type',
            'visibility_scope',
            'service_title',
            'country_name',
            'port_name',
            'country_names',
            'ports_by_country',
            'category_ids',
            'subcategory_ids',
            'brand_ids',
            'requisition_date',
            'due_date',
            'priority',
            'status',
            'items_count',
            'updated_at',
        ];

        if ($sellerListings->isEmpty()) {
            return collect();
        }

        return Rfq::query()
            ->select($rfqColumns)
            ->publicMarketplace()
            ->published()
            ->withCount('awards')
            ->withCount(['awards as confirmed_awards_count' => fn ($awardQuery) => $awardQuery->where('status', 'confirmed')])
            ->latest('updated_at')
            ->get()
            ->filter(fn (Rfq $candidate) => $candidate->canReceiveSupplierResponses())
            ->map(function (Rfq $candidate) use ($user, $sellerListings) {
                $listing = $this->firstMatchingPublicListingForSellerFromPool($candidate, $user, $sellerListings);

                if (! $listing) {
                    return null;
                }

                return [
                    'rfq' => $candidate,
                    'listing' => $listing,
                ];
            })
            ->filter()
            ->values();
    }

    private function matchingPublicListingsForSellerFromPool(
        Rfq $rfq,
        User $user,
        Collection $sellerListings
    ): Collection {
        if (! $user->isSeller() || ! $rfq->isPublicMarketplace()) {
            return collect();
        }

        $selectedCountries = collect($rfq->country_names ?? [])
            ->map(fn ($country) => CountryNameResolver::resolve((string) $country) ?? trim((string) $country))
            ->filter()
            ->unique()
            ->values();

        $selectedCountryCodes = $selectedCountries
            ->map(fn (string $country) => CountryNameResolver::codeForName($country))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $selectedPorts = $this->selectedRequestPorts($rfq);

        if ($selectedCountries->isEmpty() || $selectedPorts->isEmpty()) {
            return collect();
        }

        $selectedCategoryIds = collect($rfq->category_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $selectedSubcategoryIds = collect($rfq->subcategory_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $selectedBrandIds = collect($rfq->brand_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $sellerBrandIds = collect($user->service_brand_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedBrandIds->isNotEmpty() && $sellerBrandIds->intersect($selectedBrandIds)->isEmpty()) {
            return collect();
        }

        return $sellerListings
            ->when($selectedCategoryIds->isNotEmpty(), fn (Collection $listings) => $listings
                ->filter(fn (SupplierServiceListing $listing) => in_array((int) $listing->category_id, $selectedCategoryIds->all(), true)))
            ->when($selectedSubcategoryIds->isNotEmpty(), fn (Collection $listings) => $listings
                ->filter(fn (SupplierServiceListing $listing) => in_array((int) $listing->subcategory_id, $selectedSubcategoryIds->all(), true)))
            ->filter(function (SupplierServiceListing $listing) use ($selectedCountries, $selectedCountryCodes, $selectedPorts) {
                return $listing->ports->contains(function ($port) use ($selectedCountries, $selectedCountryCodes, $selectedPorts) {
                    $portCountry = CountryNameResolver::resolve((string) ($port->country_name ?: $port->country_code))
                        ?? $port->country_name
                        ?? $port->country_code;

                    $countryMatches = $selectedCountries->contains($portCountry)
                        || ($port->country_code && in_array((string) $port->country_code, $selectedCountryCodes, true));

                    if (! $countryMatches) {
                        return false;
                    }

                    return $selectedPorts->contains(function (array $selectedPort) use ($port) {
                        $selectedUnlocode = strtoupper(trim((string) ($selectedPort['unlocode'] ?? '')));
                        $listingUnlocode = strtoupper(trim((string) ($port->unlocode ?? '')));
                        $selectedName = mb_strtolower(trim((string) ($selectedPort['name'] ?? '')));
                        $listingName = mb_strtolower(trim((string) ($port->port_name ?? '')));

                        if ($selectedUnlocode !== '' && $listingUnlocode !== '' && $selectedUnlocode === $listingUnlocode) {
                            return true;
                        }

                        return $selectedName !== '' && $listingName !== '' && $selectedName === $listingName;
                    });
                });
            })
            ->values();
    }

    private function firstMatchingPublicListingForSellerFromPool(
        Rfq $rfq,
        User $user,
        Collection $sellerListings
    ): ?SupplierServiceListing
    {
        return $this->matchingPublicListingsForSellerFromPool($rfq, $user, $sellerListings)->first();
    }

    private function visibleSellerListingsForUser(User $user): Collection
    {
        if (! $user->isSeller() || ! $user->isApproved()) {
            return collect();
        }

        return SupplierServiceListing::query()
            ->visible()
            ->where('seller_id', $user->id)
            ->with('ports')
            ->get();
    }

    private function selectedRequestPorts(Rfq $rfq): Collection
    {
        return collect($rfq->ports_by_country ?? [])
            ->flatMap(function ($ports, $country) {
                $resolvedCountry = CountryNameResolver::resolve((string) $country) ?? trim((string) $country);

                return collect($ports ?? [])
                    ->map(fn ($port) => [
                        'country' => $resolvedCountry,
                        'name' => trim((string) data_get($port, 'name', '')),
                        'unlocode' => trim((string) data_get($port, 'unlocode', '')),
                    ]);
            })
            ->filter(fn (array $port) => $port['name'] !== '' || $port['unlocode'] !== '')
            ->values();
    }
}
