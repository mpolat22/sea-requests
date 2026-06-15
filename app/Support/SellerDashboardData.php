<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\RfqSupplierRecipient;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SellerDashboardData
{
    public function __construct(
        protected OfferInvoiceData $offerInvoiceData,
        protected OfferOrderWorkflow $workflow,
        protected OfferInvoiceTotals $invoiceTotals
    ) {}

    public function profile(User $user): array
    {
        $storageUrl = static fn (?string $path) => $path ? '/storage/'.ltrim($path, '/') : null;
        $incomingRequestsCount = $this->incomingRequestsCount($user);
        $ordersCount = $this->ordersCount($user);
        $reviewsCount = $user->receivedReviews()->count();

        $categoryIds = collect($user->service_category_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        $subcategoryIds = collect($user->service_subcategory_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $subcategories = Subcategory::query()
            ->whereIn('id', $subcategoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category_id']);

        $brandIds = collect($user->service_brand_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        $brands = Brand::query()
            ->whereIn('id', $brandIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $serviceCards = $categories->flatMap(function (Category $category) use ($subcategories, $user, $storageUrl) {
            $matchingSubcategories = $subcategories
                ->filter(fn (Subcategory $subcategory) => (int) $subcategory->category_id === (int) $category->id)
                ->values();

            if ($matchingSubcategories->isEmpty()) {
                return [[
                    'id' => "{$user->id}-category-{$category->id}",
                    'seller_id' => $user->id,
                    'title' => $category->name,
                    'company_name' => $user->company_name ?: $user->name,
                    'country' => CountryNameResolver::resolve((string) $user->country),
                    'summary' => $user->company_overview ?: $user->company_description,
                    'primary_category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ],
                    'secondary_category' => null,
                    'logo_url' => $storageUrl($user->company_logo_path),
                    'href' => ServiceRoute::url($user, $category),
                ]];
            }

            return $matchingSubcategories->map(fn (Subcategory $subcategory) => [
                'id' => "{$user->id}-subcategory-{$subcategory->id}",
                'seller_id' => $user->id,
                'title' => $subcategory->name,
                'company_name' => $user->company_name ?: $user->name,
                'country' => CountryNameResolver::resolve((string) $user->country),
                'summary' => $user->company_overview ?: $user->company_description,
                'primary_category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
                'secondary_category' => [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'slug' => $subcategory->slug,
                ],
                'logo_url' => $storageUrl($user->company_logo_path),
                'href' => ServiceRoute::url($user, $category, $subcategory),
            ])->all();
        })->values();

        $portsByCountry = $user->servicePorts()
            ->orderBy('country_name')
            ->orderBy('port_name')
            ->get(['ports.id', 'ports.country_code', 'ports.country_name', 'ports.port_name'])
            ->groupBy('country_code')
            ->map(fn ($ports) => [
                'country_code' => $ports->first()?->country_code,
                'country_name' => CountryNameResolver::resolve((string) ($ports->first()?->country_code ?? $ports->first()?->country_name)),
                'ports' => $ports->pluck('port_name')->filter()->values()->all(),
            ])
            ->values();

        return [
            'company_name' => $user->company_name ?: $user->name,
            'contact_name' => $user->name,
            'registered_at' => optional($user->created_at)?->toDateString(),
            'approval_status' => $user->approval_status,
            'approved_at' => optional($user->approved_at)?->toDateString(),
            'submitted_at' => optional($user->seller_verification_submitted_at)?->toDateString(),
            'rejection_feedback' => [
                'reason' => $user->seller_rejection_reason,
                'note' => $user->seller_rejection_note,
                'fields' => $user->seller_rejection_fields ?? [],
                'rejected_at' => optional($user->seller_rejected_at)?->toDateString(),
            ],
            'country' => CountryNameResolver::resolve((string) $user->country),
            'city' => $user->company_city,
            'address' => $user->company_address_line,
            'overview' => $user->company_overview ?: $user->company_description,
            'logo_url' => $storageUrl($user->company_logo_path),
            'website_url' => $user->website_url,
            'contact_email' => $user->contact_email ?: $user->email,
            'phone' => $user->phone,
            'whatsapp_number' => $user->whatsapp_number,
            'stats' => [
                'services' => $serviceCards->count(),
                'categories' => $categories->count(),
                'countries' => collect($user->service_country_codes ?? [])->filter()->count(),
                'ports' => $user->servicePorts()->count(),
                'documents' => collect([
                    ...($user->company_registration_documents ?? []),
                    ...($user->tax_certificate_documents ?? []),
                    ...($user->service_authorization_documents ?? []),
                ])->count(),
                'notifications' => $user->unreadNotifications()->count(),
            ],
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values(),
            'subcategories' => $subcategories->map(fn (Subcategory $subcategory) => [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'category_id' => $subcategory->category_id,
            ])->values(),
            'brands' => $brands->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
            ])->values(),
            'coverage' => $portsByCountry,
            'services_preview' => $serviceCards->take(4)->values(),
            'public_profile_url' => $serviceCards->first()['href'] ?? route('services.index'),
            'edit_url' => $user->seller_update_request_status === 'pending' ? null : route('seller.verification.create'),
            'update_request' => [
                'status' => $user->seller_update_request_status === 'pending' ? 'pending' : null,
                'requested_at' => optional($user->seller_update_requested_at)?->toDateString(),
                'changed_fields' => array_keys($user->seller_update_request_diff ?? []),
                'rejection_reason' => $user->seller_update_rejection_reason,
                'rejection_note' => $user->seller_update_rejection_note,
                'rejection_fields' => $user->seller_update_rejection_fields ?? [],
                'rejected_at' => optional($user->seller_update_rejected_at)?->toDateString(),
            ],
            'navigation' => [
                'business_url' => route('seller.dashboard'),
                'incoming_url' => route('seller.requests'),
                'incoming_count' => $incomingRequestsCount,
                'orders_url' => route('seller.orders'),
                'orders_count' => $ordersCount,
                'reviews_url' => route('seller.reviews'),
                'reviews_count' => $reviewsCount,
            ],
        ];
    }

    public function workspace(User $user): array
    {
        $incomingRequestsCount = $this->incomingRequestsCount($user);
        $ordersCount = $this->ordersCount($user);
        $reviewsCount = $user->receivedReviews()->count();

        return [
            'company_name' => $user->company_name ?: $user->name,
            'approval_status' => $user->approval_status,
            'rejection_feedback' => [
                'reason' => $user->seller_rejection_reason,
                'note' => $user->seller_rejection_note,
                'fields' => $user->seller_rejection_fields ?? [],
                'rejected_at' => optional($user->seller_rejected_at)?->toDateString(),
            ],
            'update_request' => [
                'status' => $user->seller_update_request_status === 'pending' ? 'pending' : null,
                'requested_at' => optional($user->seller_update_requested_at)?->toDateString(),
                'changed_fields' => array_keys($user->seller_update_request_diff ?? []),
                'rejection_reason' => $user->seller_update_rejection_reason,
                'rejection_note' => $user->seller_update_rejection_note,
                'rejection_fields' => $user->seller_update_rejection_fields ?? [],
                'rejected_at' => optional($user->seller_update_rejected_at)?->toDateString(),
            ],
            'navigation' => [
                'business_url' => route('seller.dashboard'),
                'incoming_url' => route('seller.requests'),
                'incoming_count' => $incomingRequestsCount,
                'orders_url' => route('seller.orders'),
                'orders_count' => $ordersCount,
                'reviews_url' => route('seller.reviews'),
                'reviews_count' => $reviewsCount,
            ],
        ];
    }

    public function dashboard(User $user): array
    {
        $incomingRequestsCount = $this->incomingRequestsCount($user);
        $ordersCount = $this->ordersCount($user);
        $reviewsCount = $user->receivedReviews()->count();

        $categoryIds = collect($user->service_category_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        $subcategoryIds = collect($user->service_subcategory_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        $brandIds = collect($user->service_brand_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        return [
            'company_name' => $user->company_name ?: $user->name,
            'registered_at' => optional($user->created_at)?->toDateString(),
            'approval_status' => $user->approval_status,
            'rejection_feedback' => [
                'reason' => $user->seller_rejection_reason,
                'note' => $user->seller_rejection_note,
                'fields' => $user->seller_rejection_fields ?? [],
                'rejected_at' => optional($user->seller_rejected_at)?->toDateString(),
            ],
            'stats' => [
                'countries' => collect($user->service_country_codes ?? [])->filter()->count(),
                'ports' => $user->servicePorts()->count(),
                'categories' => $categoryIds->isEmpty()
                    ? 0
                    : Category::query()->whereIn('id', $categoryIds)->count(),
                'subcategories' => $subcategoryIds->isEmpty()
                    ? 0
                    : Subcategory::query()->whereIn('id', $subcategoryIds)->count(),
                'brands' => $brandIds->isEmpty()
                    ? 0
                    : Brand::query()->whereIn('id', $brandIds)->count(),
            ],
            'public_profile_url' => ServiceRoute::firstProfileUrl($user),
            'edit_url' => $user->seller_update_request_status === 'pending' ? null : route('seller.verification.create'),
            'update_request' => [
                'status' => $user->seller_update_request_status === 'pending' ? 'pending' : null,
                'requested_at' => optional($user->seller_update_requested_at)?->toDateString(),
                'changed_fields' => array_keys($user->seller_update_request_diff ?? []),
                'rejection_reason' => $user->seller_update_rejection_reason,
                'rejection_note' => $user->seller_update_rejection_note,
                'rejection_fields' => $user->seller_update_rejection_fields ?? [],
                'rejected_at' => optional($user->seller_update_rejected_at)?->toDateString(),
            ],
            'navigation' => [
                'business_url' => route('seller.dashboard'),
                'incoming_url' => route('seller.requests'),
                'incoming_count' => $incomingRequestsCount,
                'orders_url' => route('seller.orders'),
                'orders_count' => $ordersCount,
                'reviews_url' => route('seller.reviews'),
                'reviews_count' => $reviewsCount,
            ],
        ];
    }

    public function incomingRequests(User $user)
    {
        $rfqAccess = app(RfqAccessService::class);
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
            'requisition_date',
            'due_date',
            'priority',
            'status',
            'items_count',
            'updated_at',
        ];

        $recipients = RfqSupplierRecipient::query()
            ->select(['id', 'rfq_id', 'supplier_service_listing_id'])
            ->where('seller_id', $user->id)
            ->with([
                'rfq' => fn ($query) => $query
                    ->select($rfqColumns)
                    ->withCount('awards')
                    ->withCount(['awards as confirmed_awards_count' => fn ($awardQuery) => $awardQuery->where('status', 'confirmed')]),
                'listing:id',
                'listing.ports:id,country_code,country_name,port_name,unlocode',
            ])
            ->latest('created_at')
            ->get()
            ->filter(fn (RfqSupplierRecipient $recipient) => $recipient->rfq !== null)
            ->unique('rfq_id')
            ->values();

        $livePublicMatches = $rfqAccess->publicIncomingRequestsForSeller($user)
            ->reject(fn (array $match) => $recipients->contains(
                fn (RfqSupplierRecipient $recipient) => (int) $recipient->rfq_id === (int) $match['rfq']->id
            ))
            ->values();

        $rfqIds = $recipients->pluck('rfq_id')
            ->merge($livePublicMatches->pluck('rfq.id'))
            ->filter()
            ->unique()
            ->values();

        $offersByRfq = Offer::query()
            ->where('seller_id', $user->id)
            ->whereIn('rfq_id', $rfqIds)
            ->get(['rfq_id', 'status'])
            ->keyBy('rfq_id');

        return $recipients
            ->map(fn (RfqSupplierRecipient $recipient) => $this->serializeIncomingRequest(
                rfq: $recipient->rfq,
                offer: $offersByRfq->get($recipient->rfq->id),
                coveragePortsByCountry: $this->coveragePortsByCountry($recipient),
                rowId: "recipient-{$recipient->id}",
            ))
            ->concat(
                $livePublicMatches->map(fn (array $match) => $this->serializeIncomingRequest(
                    rfq: $match['rfq'],
                    offer: $offersByRfq->get($match['rfq']->id),
                    coveragePortsByCountry: $this->coveragePortsByCountryFromListing($match['listing']),
                    rowId: "public-{$match['rfq']->id}-seller-{$user->id}",
                ))
            )
            ->filter(fn (array $request) => ! in_array($request['status'], ['award_confirmed', 'completed'], true))
            ->sortByDesc(fn (array $request) => $request['updated_at'] ?? '')
            ->values();
    }

    public function incomingRequestsCount(User $user): int
    {
        if (! $user->isSeller()) {
            return 0;
        }

        $recipientCount = Rfq::query()
            ->whereHas('supplierRecipients', fn (Builder $query) => $query->where('seller_id', $user->id))
            ->whereDoesntHave('awards', fn (Builder $query) => $query->where('status', OfferAward::STATUS_CONFIRMED))
            ->distinct('id')
            ->count('id');

        $recipientRfqIds = RfqSupplierRecipient::query()
            ->where('seller_id', $user->id)
            ->pluck('rfq_id')
            ->filter()
            ->unique()
            ->values();

        $rfqAccess = app(RfqAccessService::class);
        $publicMatchCount = $rfqAccess->publicIncomingRequestsForSeller($user)
            ->reject(fn (array $match) => $recipientRfqIds->contains((int) $match['rfq']->id))
            ->count();

        return $recipientCount + $publicMatchCount;
    }

    private function serializeIncomingRequest(
        Rfq $rfq,
        ?Offer $offer,
        array $coveragePortsByCountry,
        string $rowId,
    ): array {
        $offerStatus = $offer?->status ?: 'not_started';
        $countryNames = collect($rfq->country_names ?? [])
            ->filter()
            ->values();
        $portsByCountry = $this->normalizedPortsByCountry($rfq);
        $matchedCoveragePortsByCountry = $this->matchedCoveragePortsByCountry(
            $countryNames->all(),
            $portsByCountry,
            $coveragePortsByCountry
        );
        $matchedCoverageCountries = $this->coverageCountriesFromGroups($matchedCoveragePortsByCountry);

        return [
            'id' => $rowId,
            'rfq_id' => $rfq->id,
            'reference_no' => $rfq->reference_no,
            'show_url' => route('seller.rfqs.show', $rfq),
            'offer_url' => route('seller.offers.create', $rfq),
            'request_type' => $rfq->request_type,
            'is_private_request' => $rfq->isPrivateSupplierRequest(),
            'service_title' => $rfq->service_title,
            'country_name' => $this->countrySummaryForDashboard($countryNames, $rfq->country_name),
            'port_name' => $this->portSummaryForDashboard($portsByCountry, $rfq->port_name),
            'request_countries' => $countryNames->all(),
            'request_ports_by_country' => $portsByCountry,
            'request_port_totals_by_country' => $this->activePortCountsForCountries($countryNames->all()),
            'matched_coverage_countries' => $matchedCoverageCountries,
            'matched_coverage_ports_by_country' => $matchedCoveragePortsByCountry,
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'priority' => $rfq->priority,
            'status' => $rfq->supplierDashboardStatus(),
            'my_offer_status' => $offerStatus,
            'response_allowed' => $rfq->canReceiveSupplierResponses(),
            'updated_at' => optional($rfq->updated_at)?->toISOString(),
        ];
    }

    public function orders(User $user)
    {
        return $this->orderSummariesQuery($user)
            ->orderByDesc('latest_confirmed_at')
            ->get()
            ->map(fn (Offer $offer) => $this->mapOrderSummary($offer))
            ->filter()
            ->values();
    }

    public function order(User $user, Offer $offer): ?array
    {
        $rows = $this->confirmedAwardRowsQuery($user)
            ->where('offer_id', $offer->id)
            ->latest('confirmed_at')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return $this->mapAwardedOrderRows($rows);
    }

    private function orderSummariesQuery(User $user): Builder
    {
        return Offer::query()
            ->select([
                'id',
                'rfq_id',
                'seller_id',
                'request_type',
                'currency',
                'order_workflow_status',
                'including_tax',
                'tax_amount',
                'including_packing',
                'packing_cost',
                'including_freight',
                'freight_cost',
                'including_mobilization',
                'mobilization_cost',
                'total_offer_amount',
                'grand_total',
            ])
            ->where('seller_id', $user->id)
            ->whereHas('awards', fn (Builder $query) => $query->where('status', OfferAward::STATUS_CONFIRMED))
            ->withCount('invoices')
            ->with([
                'rfq:id,reference_no,request_type,visibility_scope,company_name,ship_name,service_title,currency',
                'awards' => fn ($query) => $query
                    ->select(['id', 'offer_id', 'offer_item_id', 'awarded_quantity', 'confirmed_at'])
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->orderByDesc('confirmed_at'),
                'items' => fn ($query) => $query
                    ->select(['id', 'offer_id', 'rfq_item_id', 'unit_price'])
                    ->with(['rfqItem:id,product_name']),
            ])
            ->withMax([
                'awards as latest_confirmed_at' => fn ($query) => $query
                    ->where('status', OfferAward::STATUS_CONFIRMED),
            ], 'confirmed_at');
    }

    private function confirmedAwardRowsQuery(User $user): Builder
    {
        return OfferAward::query()
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->whereHas('offer', fn ($query) => $query->where('seller_id', $user->id))
            ->with([
                'rfq.attachments:id,rfq_id,disk,path,original_name,mime_type,size',
                'rfqItem.attachments:id,rfq_item_id,disk,path,original_name,mime_type,size',
                'offer.attachments:id,offer_id,disk,path,original_name,mime_type,size',
                'offer.invoices',
                'offerItem' => fn ($query) => $query->with([
                    'rfqItem.attachments:id,rfq_item_id,disk,path,original_name,mime_type,size',
                    'attachments:id,offer_item_id,disk,path,original_name,mime_type,size',
                ]),
            ]);
    }

    public function ordersCount(User $user): int
    {
        if (! $user->isSeller()) {
            return 0;
        }

        return OfferAward::query()
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->whereHas('offer', fn (Builder $query) => $query->where('seller_id', $user->id))
            ->distinct('offer_id')
            ->count('offer_id');
    }

    public function awardedRequests(User $user)
    {
        return $this->orders($user);
    }

    private function mapOrderSummary(Offer $offer): ?array
    {
        $rfq = $offer->rfq;

        if (! $rfq) {
            return null;
        }

        $offerItemsById = $offer->items->keyBy('id');
        $selectedProductNames = $offer->awards
            ->filter(fn (OfferAward $award) => $award->offer_item_id !== null)
            ->map(fn (OfferAward $award) => $offerItemsById->get($award->offer_item_id)?->rfqItem?->product_name)
            ->filter()
            ->unique()
            ->values();

        $selectedTotal = $offer->request_type === 'service_request'
            ? (float) ($offer->grand_total ?? 0)
            : $offer->awards->sum(fn (OfferAward $award) => (float) ($award->awarded_quantity ?? 0) * (float) ($offerItemsById->get($award->offer_item_id)?->unit_price ?? 0));

        $agreedInvoiceTotal = $this->invoiceTotals->agreedTotal($offer);
        $invoicesCount = (int) ($offer->invoices_count ?? 0);
        $hasInvoices = $invoicesCount > 0;
        $orderWorkflowStatus = $this->summaryOrderWorkflowStatus($offer, $invoicesCount);

        return [
            'id' => $offer->id,
            'offer_id' => $offer->id,
            'reference_no' => $rfq->reference_no,
            'show_url' => route('seller.rfqs.show', $rfq),
            'order_url' => route('seller.orders.show', $offer),
            'modal_url' => route('seller.orders.modal', $offer),
            'request_type' => $rfq->request_type,
            'is_private_request' => $rfq->isPrivateSupplierRequest(),
            'company_name' => $rfq->company_name,
            'ship_name' => $rfq->ship_name,
            'service_title' => $rfq->service_title,
            'confirmed_at' => $this->isoString($offer->latest_confirmed_at),
            'currency' => $offer->currency ?: $rfq->currency ?: 'USD',
            'selected_total' => $this->decimalString($selectedTotal),
            'agreed_invoice_total' => $this->decimalString($agreedInvoiceTotal),
            'selected_item_names' => $selectedProductNames->all(),
            'order_workflow_status' => $orderWorkflowStatus,
            'order_workflow_status_label' => $this->workflow->label($orderWorkflowStatus),
            'can_manage_invoices' => $offer->hasCompleteOrderInformation() && $orderWorkflowStatus !== Offer::ORDER_STATUS_COMPLETED,
            'has_invoices' => $hasInvoices,
        ];
    }

    private function mapAwardedOrderRows($rows): ?array
    {
        /** @var \App\Models\OfferAward|null $latestAward */
        $latestAward = $rows
            ->sortByDesc(fn (OfferAward $award) => optional($award->confirmed_at)?->getTimestamp() ?? 0)
            ->first();

        $offer = $latestAward?->offer;
        $rfq = $latestAward?->rfq ?: $offer?->rfq;

        if (! $latestAward || ! $offer || ! $rfq) {
            return null;
        }

        $selectedItems = $rows
            ->filter(fn (OfferAward $award) => $award->offer_item_id !== null)
            ->map(function (OfferAward $award) {
                $offerItem = $award->offerItem;
                $rfqItem = $offerItem?->rfqItem ?: $award->rfqItem;
                $selectedQty = (float) ($award->awarded_quantity ?? 0);
                $unitPrice = (float) ($offerItem?->unit_price ?? 0);

                return [
                    'offer_item_id' => $award->offer_item_id,
                    'rfq_item_id' => $award->rfq_item_id,
                    'line_no' => $rfqItem?->line_no ?? $offerItem?->line_no,
                    'product_name' => $rfqItem?->product_name ?: '-',
                    'part_no' => $rfqItem?->part_no ?: '',
                    'requested_manufacturer' => $rfqItem?->manufacturer ?: '',
                    'requested_quantity' => $rfqItem?->quantity !== null ? $this->decimalString($rfqItem->quantity) : '',
                    'unit' => $rfqItem?->unit ?: '',
                    'requested_quality' => $rfqItem?->quality ?: '',
                    'requested_comments' => $rfqItem?->comments ?: '',
                    'request_attachments' => $this->serializeAttachments($rfqItem?->attachments),
                    'offered_manufacturer' => $offerItem?->manufacturer ?: '',
                    'offered_qty' => $offerItem?->offer_qty !== null ? $this->decimalString($offerItem->offer_qty) : '',
                    'offered_quality' => $offerItem?->quality ?: '',
                    'delivery_time' => $offerItem?->delivery_time ?: '',
                    'offer_remarks' => $offerItem?->remarks ?: '',
                    'offer_attachments' => $this->serializeAttachments($offerItem?->attachments),
                    'selected_qty' => $this->decimalString($selectedQty),
                    'unit_price' => $this->decimalString($unitPrice),
                    'line_total' => $this->decimalString(round($selectedQty * $unitPrice, 2)),
                    'buyer_note' => $award->buyer_note ?? '',
                ];
            })
            ->values();

        $selectedTotal = $rfq->request_type === 'service_request'
            ? (float) ($offer->grand_total ?? 0)
            : $selectedItems->sum(fn (array $item) => (float) $item['line_total']);

        $serializedInvoices = $offer->invoices
            ->map(fn ($invoice) => $this->offerInvoiceData->forSeller($invoice, $offer))
            ->values();
        $agreedInvoiceTotal = $this->invoiceTotals->agreedTotal($offer);
        $invoicedTotal = $this->invoiceTotals->invoicedTotal($offer);
        $remainingInvoiceTotal = $this->invoiceTotals->remainingTotal($offer);

        $serviceAward = $rfq->request_type === 'service_request'
            ? $rows->first()
            : null;

        return [
            'id' => $offer->id,
            'offer_id' => $offer->id,
            'rfq_id' => $rfq->id,
            'reference_no' => $rfq->reference_no,
            'order_url' => route('seller.orders.show', $offer),
            'create_invoice_url' => route('seller.orders.invoices.store', $offer),
            'show_url' => route('seller.rfqs.show', $rfq),
            'request_type' => $rfq->request_type,
            'is_private_request' => $rfq->isPrivateSupplierRequest(),
            'company_name' => $rfq->company_name,
            'ship_name' => $rfq->ship_name,
            'service_title' => $rfq->service_title,
            'service_description' => $rfq->service_description ?? '',
            'country_names' => collect($rfq->country_names ?? [])->filter()->values()->all(),
            'ports_by_country' => $this->normalizedPortsByCountry($rfq),
            'port_totals_by_country' => $this->activePortCountsForCountries(
                collect($rfq->country_names ?? [])->filter()->values()->all()
            ),
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'priority' => $rfq->priority,
            'general_notes' => $rfq->general_notes ?? '',
            'confirmed_at' => optional($latestAward->confirmed_at)?->toISOString(),
            'currency' => $offer->currency ?: $rfq->currency ?: 'USD',
            'selected_total' => $this->decimalString($selectedTotal),
            'total_offer_amount' => $this->decimalString($offer->total_offer_amount),
            'including_tax' => (bool) $offer->including_tax,
            'tax_amount' => $this->decimalString($offer->tax_amount),
            'including_packing' => (bool) $offer->including_packing,
            'packing_cost' => $this->decimalString($offer->packing_cost),
            'including_freight' => (bool) $offer->including_freight,
            'freight_cost' => $this->decimalString($offer->freight_cost),
            'including_mobilization' => (bool) $offer->including_mobilization,
            'mobilization_cost' => $this->decimalString($offer->mobilization_cost),
            'delivery_terms' => $offer->delivery_terms ?? '',
            'other_delivery_terms' => $offer->other_delivery_terms ?? '',
            'award_scope_policy' => $rfq->request_type === 'service_request'
                ? Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED
                : $offer->awardScopePolicy(),
            'payment_terms_summary' => $this->paymentTermsSummary($offer),
            'other_payment_terms' => $offer->other_payment_terms ?? '',
            'completion_time' => $offer->completion_time ?? '',
            'offer_validity' => $offer->offer_validity ?? '',
            'order_workflow_status' => $this->orderWorkflowStatus($offer),
            'order_workflow_status_label' => $this->orderWorkflowStatusLabel($offer),
            'can_manage_invoices' => $offer->canSellerManageInvoices(),
            'can_add_invoice' => $this->invoiceTotals->canAddInvoice($offer),
            'agreed_invoice_total' => $this->decimalString($agreedInvoiceTotal),
            'invoiced_total' => $this->decimalString($invoicedTotal),
            'remaining_invoice_total' => $this->decimalString($remainingInvoiceTotal),
            'invoices' => $serializedInvoices->all(),
            'billing_company_name' => $offer->billing_company_name ?? '',
            'billing_address' => $offer->billing_address ?? '',
            'billing_tax_id' => $offer->billing_tax_id ?? '',
            'billing_contact_name' => $offer->billing_contact_name ?? '',
            'billing_contact_email' => $offer->billing_contact_email ?? '',
            'billing_contact_phone' => $offer->billing_contact_phone ?? '',
            'delivery_target_type' => $offer->delivery_target_type ?? '',
            'delivery_country' => $offer->delivery_country ?? '',
            'delivery_port' => $offer->delivery_port ?? '',
            'delivery_address' => $offer->delivery_address ?? '',
            'delivery_contact_name' => $offer->delivery_contact_name ?? '',
            'delivery_contact_email' => $offer->delivery_contact_email ?? '',
            'delivery_contact_phone' => $offer->delivery_contact_phone ?? '',
            'delivery_required_date' => optional($offer->delivery_required_date)?->format('Y-m-d'),
            'service_location_type' => $offer->service_location_type ?? '',
            'service_location' => $offer->service_location ?? '',
            'service_contact_name' => $offer->service_contact_name ?? '',
            'service_contact_email' => $offer->service_contact_email ?? '',
            'service_contact_phone' => $offer->service_contact_phone ?? '',
            'service_required_date' => optional($offer->service_required_date)?->format('Y-m-d'),
            'service_instruction_notes' => $offer->service_instruction_notes ?? '',
            'general_note' => $offer->general_note ?? '',
            'service_clarification' => $offer->service_clarification ?? '',
            'request_attachments' => $this->serializeAttachments($rfq->attachments),
            'offer_attachments' => $this->serializeAttachments($offer->attachments),
            'selected_items_count' => $selectedItems->count(),
            'selected_items' => $selectedItems,
            'buyer_note' => $serviceAward?->buyer_note ?? '',
        ];
    }

    private function paymentTermsSummary(Offer $offer): string
    {
        $parts = [];

        if ((float) ($offer->payment_order_confirmation ?? 0) > 0) {
            $parts[] = $this->decimalString($offer->payment_order_confirmation).'% when order confirmation';
        }

        if ((float) ($offer->payment_before_shipment ?? 0) > 0) {
            $parts[] = $this->decimalString($offer->payment_before_shipment).'% before shipment';
        }

        if ((int) ($offer->payment_invoice_days ?? 0) > 0) {
            $parts[] = (int) $offer->payment_invoice_days.' days from Invoice Date';
        }

        if (filled($offer->other_payment_terms)) {
            $parts[] = 'Other: '.trim((string) $offer->other_payment_terms);
        }

        return count($parts) ? implode(' / ', $parts) : '-';
    }

    private function orderWorkflowStatus(Offer $offer): string
    {
        return $this->workflow->resolveStatus($offer);
    }

    private function summaryOrderWorkflowStatus(Offer $offer, int $invoicesCount): string
    {
        if (filled($offer->order_workflow_status)) {
            return (string) $offer->order_workflow_status;
        }

        if (! $offer->hasCompleteOrderInformation()) {
            return Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING;
        }

        return $invoicesCount > 0
            ? Offer::ORDER_STATUS_INVOICE_UPLOADED
            : Offer::ORDER_STATUS_INVOICE_PENDING;
    }

    private function serializeAttachments($attachments): array
    {
        return collect($attachments ?? [])
            ->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'url' => Storage::disk($attachment->disk)->url($attachment->path),
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ])
            ->values()
            ->all();
    }

    private function orderWorkflowStatusLabel(Offer $offer): string
    {
        return $this->workflow->label($this->orderWorkflowStatus($offer));
    }

    private function decimalString($value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function isoString($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return \Illuminate\Support\Carbon::parse((string) $value)->toISOString();
    }

    private function countrySummaryForDashboard($countryNames, ?string $fallback = null): string
    {
        $countries = collect($countryNames)
            ->filter()
            ->values();

        if ($countries->isEmpty()) {
            return $fallback ?: '-';
        }

        if ($countries->count() === 1) {
            return (string) $countries->first();
        }

        return $countries->count().' countries';
    }

    private function portSummaryForDashboard(array $portsByCountry, ?string $fallback = null): string
    {
        $portNames = collect($portsByCountry)
            ->flatMap(fn (array $group) => collect($group['ports'] ?? [])->pluck('name'))
            ->filter()
            ->values();

        if ($portNames->isEmpty()) {
            return $fallback ?: '-';
        }

        if ($portNames->count() === 1) {
            return (string) $portNames->first();
        }

        return $portNames->count().' ports';
    }

    private function coveragePortsByCountry(RfqSupplierRecipient $recipient): array
    {
        return $this->coveragePortsByCountryFromListing($recipient->listing);
    }

    private function coveragePortsByCountryFromListing(?SupplierServiceListing $listing): array
    {
        return collect($listing?->ports ?? [])
            ->filter(fn ($port) => filled($port->port_name) || filled($port->country_name) || filled($port->country_code))
            ->sortBy(fn ($port) => sprintf(
                '%s|%s',
                mb_strtolower((string) CountryNameResolver::resolve((string) ($port->country_name ?: $port->country_code))),
                mb_strtolower((string) $port->port_name)
            ))
            ->groupBy(fn ($port) => CountryNameResolver::resolve((string) ($port->country_name ?: $port->country_code)))
            ->map(function ($ports, $country) {
                return [
                    'country' => $country,
                    'ports' => collect($ports)
                        ->map(fn ($port) => [
                            'id' => null,
                            'name' => $port->port_name,
                            'unlocode' => $port->unlocode,
                        ])
                        ->filter(fn ($port) => filled($port['name'] ?? null))
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $group) => filled($group['country']) || count($group['ports']) > 0)
            ->values()
            ->all();
    }

    private function coverageCountriesFromGroups(array $groups): array
    {
        return collect($groups)
            ->pluck('country')
            ->filter()
            ->values()
            ->all();
    }

    private function matchedCoveragePortsByCountry(
        array $requestCountries,
        array $requestPortsByCountry,
        array $coveragePortsByCountry
    ): array {
        $requestCountryKeys = collect($requestCountries)
            ->map(fn ($country) => $this->locationKey($country))
            ->filter()
            ->unique()
            ->values();

        $requestedPortsByCountry = collect($requestPortsByCountry)
            ->mapWithKeys(function (array $group) {
                $countryKey = $this->locationKey($group['country'] ?? null);

                if (! $countryKey) {
                    return [];
                }

                $ports = collect($group['ports'] ?? [])
                    ->map(fn (array $port) => [
                        'name' => $this->locationKey($port['name'] ?? null),
                        'unlocode' => $this->locationKey($port['unlocode'] ?? null),
                    ])
                    ->filter(fn (array $port) => filled($port['name']) || filled($port['unlocode']))
                    ->values()
                    ->all();

                return [$countryKey => $ports];
            })
            ->all();

        return collect($coveragePortsByCountry)
            ->map(function (array $group) use ($requestCountryKeys, $requestedPortsByCountry) {
                $country = $group['country'] ?? null;
                $countryKey = $this->locationKey($country);

                if (! $countryKey) {
                    return null;
                }

                if ($requestCountryKeys->isNotEmpty() && ! $requestCountryKeys->contains($countryKey)) {
                    return null;
                }

                $requestedPorts = $requestedPortsByCountry[$countryKey] ?? [];

                if (count($requestedPorts) === 0) {
                    return [
                        'country' => $country,
                        'ports' => [],
                    ];
                }

                $matchedPorts = collect($group['ports'] ?? [])
                    ->filter(function (array $port) use ($requestedPorts) {
                        $portNameKey = $this->locationKey($port['name'] ?? null);
                        $portUnlocodeKey = $this->locationKey($port['unlocode'] ?? null);

                        return collect($requestedPorts)->contains(function (array $requestedPort) use ($portNameKey, $portUnlocodeKey) {
                            if (filled($portUnlocodeKey) && filled($requestedPort['unlocode']) && $requestedPort['unlocode'] === $portUnlocodeKey) {
                                return true;
                            }

                            return filled($portNameKey) && filled($requestedPort['name']) && $requestedPort['name'] === $portNameKey;
                        });
                    })
                    ->values()
                    ->all();

                if (count($matchedPorts) === 0) {
                    return null;
                }

                return [
                    'country' => $country,
                    'ports' => $matchedPorts,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function locationKey($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_strtolower($text);
    }

    private function normalizedPortsByCountry($rfq): array
    {
        $stored = collect($rfq->ports_by_country ?? []);
        $portIds = $stored
            ->flatten(1)
            ->map(function ($port) {
                if (is_numeric($port)) {
                    return (int) $port;
                }

                $id = data_get($port, 'id');

                return is_numeric($id) ? (int) $id : null;
            })
            ->filter()
            ->unique()
            ->values();

        $portsById = Port::query()
            ->whereIn('id', $portIds)
            ->get(['id', 'port_name', 'unlocode'])
            ->keyBy('id');

        return $stored
            ->map(function ($ports, $country) use ($portsById) {
                return [
                    'country' => $country,
                    'ports' => collect($ports)
                        ->map(function ($port) use ($portsById) {
                            if (is_numeric($port)) {
                                $resolved = $portsById->get((int) $port);

                                return $resolved ? [
                                    'id' => $resolved->id,
                                    'name' => $resolved->port_name,
                                    'unlocode' => $resolved->unlocode,
                                ] : null;
                            }

                            $name = data_get($port, 'name');

                            if (filled($name)) {
                                return [
                                    'id' => data_get($port, 'id'),
                                    'name' => $name,
                                    'unlocode' => data_get($port, 'unlocode'),
                                ];
                            }

                            $id = data_get($port, 'id');
                            $resolved = is_numeric($id) ? $portsById->get((int) $id) : null;

                            return $resolved ? [
                                'id' => $resolved->id,
                                'name' => $resolved->port_name,
                                'unlocode' => $resolved->unlocode,
                            ] : null;
                        })
                        ->filter(fn ($port) => filled($port['name'] ?? null))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function activePortCountsForCountries(array $countryNames): array
    {
        $selectedCountries = collect($countryNames)
            ->map(fn ($country) => trim((string) $country))
            ->filter()
            ->unique()
            ->values();

        if ($selectedCountries->isEmpty()) {
            return [];
        }

        return collect($this->activePortCountsMap())
            ->only($selectedCountries->all())
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function activePortCountsMap(): array
    {
        return Cache::remember('seller_dashboard_active_port_counts_by_country_v1', now()->addMinutes(30), function (): array {
            return Port::query()
                ->active()
                ->get(['country_name'])
                ->map(function (Port $port) {
                    return CountryNameResolver::resolve($port->country_name) ?? $port->country_name;
                })
                ->filter()
                ->countBy()
                ->map(fn ($count) => (int) $count)
                ->all();
        });
    }
}
