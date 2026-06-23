<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class BuyerDashboardData
{
    protected array $supplierProfileUrls = [];

    public function __construct(
        protected SupplierReviewData $reviewData,
        protected OfferInvoiceData $offerInvoiceData,
        protected OfferOrderWorkflow $workflow,
        protected OfferInvoiceTotals $invoiceTotals
    ) {}

    public function dashboard(array $summary, array $reviewSummary, array $orderSummary): array
    {
        return [
            'navigation' => [
                'requests_url' => route('buyer.requests'),
                'requests_count' => (int) ($summary['total'] ?? 0),
                'orders_url' => route('buyer.orders'),
                'orders_count' => (int) ($orderSummary['total'] ?? 0),
                'reviews_url' => route('buyer.reviews'),
                'reviews_count' => (int) ($reviewSummary['total'] ?? 0),
            ],
        ];
    }

    public function rfqPage(User $user, ?string $search = null, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $query = $this->baseRfqQuery($user)
            ->withCount(['submittedOffers as offers_count'])
            ->withCount('awards')
            ->withCount(['awards as confirmed_awards_count' => fn ($query) => $query->where('status', 'confirmed')])
            ->withCount('supplierRecipients')
            ->latest('updated_at');

        if (filled($search)) {
            $this->applyRfqSearch($query, (string) $search);
        }

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString()
            ->through(fn (Rfq $rfq) => $this->mapRfq($rfq));
    }

    public function rfqs(User $user): Collection
    {
        return $this->baseRfqQuery($user)
            ->withCount('awards')
            ->withCount(['awards as confirmed_awards_count' => fn ($query) => $query->where('status', 'confirmed')])
            ->latest('updated_at')
            ->get()
            ->map(fn (Rfq $rfq) => $this->mapRfq($rfq))
            ->values();
    }

    public function reviews(User $user): Collection
    {
        return $this->reviewData->buyerDashboardReviews($user)
            ->map(fn (array $review) => [
                ...$review,
                'delete_review_url' => ! empty($review['review_id'])
                    ? route('supplier-reviews.destroy', $review['review_id'])
                    : null,
            ])
            ->values();
    }

    public function orders(User $user): Collection
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
        $record = $this->orderDetailQuery($user)
            ->whereKey($offer->id)
            ->first();

        return $record ? $this->mapOrder($record) : null;
    }

    private function orderSummariesQuery(User $user): Builder
    {
        return $this->baseOrderQuery($user)
            ->select([
                'id',
                'rfq_id',
                'seller_id',
                'request_type',
                'currency',
                'order_workflow_status',
                'billing_company_name',
                'billing_address',
                'billing_contact_name',
                'billing_contact_email',
                'billing_contact_phone',
                'delivery_target_type',
                'delivery_country',
                'delivery_port',
                'delivery_address',
                'delivery_contact_name',
                'delivery_contact_email',
                'delivery_contact_phone',
                'delivery_required_date',
                'service_location_type',
                'service_location',
                'service_contact_name',
                'service_contact_email',
                'service_contact_phone',
                'service_required_date',
                'payment_order_confirmation',
                'payment_before_shipment',
                'payment_invoice_days',
                'other_payment_terms',
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
            ->withCount('invoices')
            ->with([
                'rfq:id,reference_no,request_type,visibility_scope,company_name,ship_name,imo_number,service_title,currency',
                'invoices:id,offer_id,invoice_amount,payment_proof_date,payment_reference,payment_notes,payment_proof_document_path,payment_confirmed_at',
                'seller:id,name,company_name',
                'awards' => fn ($query) => $query
                    ->select(['id', 'offer_id', 'offer_item_id', 'awarded_quantity', 'confirmed_at', 'status'])
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->where('buyer_id', $user->id)
                    ->orderByDesc('confirmed_at'),
                'items' => fn ($query) => $query->select(['id', 'offer_id', 'rfq_item_id', 'unit_price']),
            ])
            ->withMax([
                'awards as latest_confirmed_at' => fn ($query) => $query
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->where('buyer_id', $user->id),
            ], 'confirmed_at');
    }

    private function orderDetailQuery(User $user): Builder
    {
        return $this->baseOrderQuery($user)
            ->with([
                'rfq.attachments:id,rfq_id,disk,path,original_name,mime_type,size',
                'seller:id,name,company_name',
                'attachments:id,offer_id,disk,path,original_name,mime_type,size',
                'invoices',
                'awards' => fn ($query) => $query
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->where('buyer_id', $user->id)
                    ->with([
                        'offerItem' => fn ($offerItemQuery) => $offerItemQuery
                            ->with([
                                'rfqItem.attachments:id,rfq_item_id,disk,path,original_name,mime_type,size',
                                'attachments:id,offer_item_id,disk,path,original_name,mime_type,size',
                            ]),
                        'rfqItem.attachments:id,rfq_item_id,disk,path,original_name,mime_type,size',
                    ])
                    ->orderBy('id'),
            ])
            ->withMax([
                'awards as latest_confirmed_at' => fn ($query) => $query
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->where('buyer_id', $user->id),
            ], 'confirmed_at');
    }

    public function requestSummary(User $user, ?Collection $rfqs = null): array
    {
        if ($rfqs !== null) {
            return [
                'total' => $rfqs->count(),
                'draft' => $rfqs->where('status', 'draft')->count(),
                'submitted' => $rfqs->whereIn('status', ['open', 'award_in_progress'])->count(),
                'closed' => $rfqs->whereIn('status', ['closed', 'cancelled', 'award_confirmed', 'completed'])->count(),
            ];
        }

        $baseQuery = $this->baseRfqQuery($user);
        $total = (clone $baseQuery)->count();
        $draft = (clone $baseQuery)
            ->where('status', Rfq::STATUS_DRAFT)
            ->count();
        $submitted = (clone $baseQuery)
            ->where('status', Rfq::STATUS_SUBMITTED)
            ->whereDoesntHave('awards', fn ($query) => $query->where('status', OfferAward::STATUS_CONFIRMED))
            ->count();

        return [
            'total' => $total,
            'draft' => $draft,
            'submitted' => $submitted,
            'closed' => max(0, $total - $draft - $submitted),
        ];
    }

    public function reviewSummary(User $user, ?Collection $reviews = null): array
    {
        if ($reviews !== null) {
            return [
                'total' => $reviews->count(),
                'pending' => $reviews->where('status', 'pending')->count(),
                'published' => $reviews->where('status', 'published')->count(),
            ];
        }

        $reviewableOffers = Offer::query()
            ->whereHas('awards', fn ($query) => $query
                ->where('buyer_id', $user->id)
                ->where('status', OfferAward::STATUS_CONFIRMED)
            );

        $total = (clone $reviewableOffers)->count();
        $published = (clone $reviewableOffers)->whereHas('review')->count();

        return [
            'total' => $total,
            'pending' => max(0, $total - $published),
            'published' => $published,
        ];
    }

    public function orderSummary(User $user, ?Collection $orders = null): array
    {
        return [
            'total' => $orders?->count() ?? $this->baseOrderQuery($user)->count(),
        ];
    }

    private function baseRfqQuery(User $user): Builder
    {
        return Rfq::query()
            ->where('buyer_id', $user->id);
    }

    private function baseOrderQuery(User $user): Builder
    {
        return Offer::query()
            ->whereHas('awards', fn ($query) => $query
                ->where('status', OfferAward::STATUS_CONFIRMED)
                ->where('buyer_id', $user->id)
            );
    }

    private function applyRfqSearch(Builder $query, string $search): void
    {
        $term = trim($search);

        if ($term === '') {
            return;
        }

        $normalized = mb_strtolower($term);
        $serviceMatch = str_contains($normalized, 'service');
        $spareMatch = str_contains($normalized, 'spare');
        $privateMatch = str_contains($normalized, 'private');

        $query->where(function (Builder $searchQuery) use ($term, $serviceMatch, $spareMatch, $privateMatch) {
            $like = '%'.$term.'%';

            $searchQuery
                ->where('reference_no', 'like', $like)
                ->orWhere('service_title', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('ship_name', 'like', $like)
                ->orWhere('country_name', 'like', $like)
                ->orWhere('port_name', 'like', $like);

            if ($serviceMatch) {
                $searchQuery->orWhere('request_type', 'service_request');
            }

            if ($spareMatch) {
                $searchQuery->orWhere('request_type', 'spare_parts');
            }

            if ($privateMatch) {
                $searchQuery->orWhere('visibility_scope', Rfq::VISIBILITY_PRIVATE_SUPPLIER);
            }
        });
    }

    private function mapRfq(Rfq $rfq): array
    {
        $offersCount = $rfq->offersCount();
        $isPrivateRequest = $rfq->isPrivateSupplierRequest();
        $status = $rfq->buyerDashboardStatus();
        $compareUrl = $offersCount > 0 && $status !== 'completed'
            ? $rfq->buyerCompareUrl()
            : null;

        return [
            'id' => $rfq->id,
            'reference_no' => $rfq->reference_no,
            'request_type' => $rfq->request_type,
            'service_title' => $rfq->service_title,
            'ship_name' => $rfq->ship_name,
            'company_name' => $rfq->company_name,
            'country_name' => $rfq->country_name,
            'port_name' => $rfq->port_name,
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'currency' => $rfq->currency,
            'priority' => $rfq->priority,
            'status' => $status,
            'items_count' => $rfq->items_count,
            'suppliers_count' => $rfq->supplier_recipients_count ?? $rfq->supplierRecipients()->count(),
            'offers_count' => $offersCount,
            'can_edit' => $rfq->canBeEdited(),
            'general_only_edit' => $rfq->canBeGeneralInfoEditedOnly(),
            'can_delete' => $rfq->canBeDeleted(),
            'edit_reason' => $rfq->editReason(),
            'show_url' => $rfq->buyerShowUrl(),
            'compare_url' => $compareUrl,
            'edit_url' => route('rfqs.edit', $rfq),
            'delete_url' => route('rfqs.destroy', $rfq),
            'visibility_scope' => $rfq->visibilityScope(),
            'is_private_request' => $isPrivateRequest,
            'visibility_badge' => $isPrivateRequest ? 'Private Request' : null,
            'request_type_badge' => $isPrivateRequest
                ? 'Private Request'
                : ($rfq->request_type === 'service_request' ? 'Service Request' : 'Spare Parts'),
            'submitted_at' => optional($rfq->submitted_at)?->toISOString(),
            'updated_at' => optional($rfq->updated_at)?->toISOString(),
        ];
    }

    private function mapOrderSummary(Offer $offer): ?array
    {
        $rfq = $offer->rfq;

        if (! $rfq) {
            return null;
        }

        $offerItemsById = $offer->items->keyBy('id');
        $awards = $offer->awards
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->values();

        if ($awards->isEmpty()) {
            return null;
        }

        $selectedTotal = $rfq->request_type === 'service_request'
            ? (float) ($offer->grand_total ?? 0)
            : $awards->sum(fn (OfferAward $award) => (float) ($award->awarded_quantity ?? 0) * (float) ($offerItemsById->get($award->offer_item_id)?->unit_price ?? 0));

        $agreedInvoiceTotal = $this->invoiceTotals->agreedTotal($offer);
        $invoicesCount = (int) ($offer->invoices_count ?? 0);
        $hasInvoices = $invoicesCount > 0;
        $orderWorkflowStatus = $this->workflow->resolveStatus($offer);

        return [
            'id' => $offer->id,
            'offer_id' => $offer->id,
            'reference_no' => $rfq->reference_no,
            'show_url' => route('buyer.orders.show', $offer),
            'modal_url' => route('buyer.orders.modal', $offer),
            'rfq_show_url' => route('buyer.rfqs.show', [
                'rfq' => $rfq,
                'offer' => $offer->id,
            ]),
            'supplier_name' => $offer->seller?->company_name ?: $offer->seller?->name ?: '-',
            'supplier_profile_url' => $this->supplierProfileUrl($offer->seller),
            'ship_name' => $rfq->ship_name,
            'imo_number' => $rfq->imo_number,
            'service_title' => $rfq->service_title,
            'confirmed_at' => $this->isoString($offer->latest_confirmed_at),
            'currency' => $offer->currency ?: $rfq->currency ?: 'USD',
            'selected_total' => $this->decimalString($selectedTotal),
            'agreed_invoice_total' => $this->decimalString($agreedInvoiceTotal),
            'selected_items_count' => $rfq->request_type === 'service_request'
                ? max(1, $awards->count())
                : $awards->whereNotNull('offer_item_id')->count(),
            'payment_terms_summary' => $this->paymentTermsSummary($offer),
            'order_workflow_status' => $orderWorkflowStatus,
            'order_workflow_status_label' => $this->workflow->label($orderWorkflowStatus),
            'can_edit_order_information' => ! $hasInvoices && $orderWorkflowStatus !== Offer::ORDER_STATUS_COMPLETED,
            'has_invoices' => $hasInvoices,
        ];
    }

    private function mapOrder(Offer $offer): ?array
    {
        $rfq = $offer->rfq;

        if (! $rfq) {
            return null;
        }

        $awards = $offer->awards
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->values();

        if ($awards->isEmpty()) {
            return null;
        }

        $selectedItems = $awards
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
            ->map(fn ($invoice) => $this->offerInvoiceData->forBuyer($invoice, $offer))
            ->values();
        $agreedInvoiceTotal = $this->invoiceTotals->agreedTotal($offer);
        $invoicedTotal = $this->invoiceTotals->invoicedTotal($offer);
        $remainingInvoiceTotal = $this->invoiceTotals->remainingTotal($offer);

        return [
            'id' => $offer->id,
            'offer_id' => $offer->id,
            'rfq_id' => $rfq->id,
            'reference_no' => $rfq->reference_no,
            'show_url' => route('buyer.orders.show', $offer),
            'update_order_information_url' => route('buyer.orders.information.update', $offer),
            'rfq_show_url' => route('buyer.rfqs.show', [
                'rfq' => $rfq,
                'offer' => $offer->id,
            ]),
            'request_type' => $rfq->request_type,
            'is_private_request' => $rfq->isPrivateSupplierRequest(),
            'supplier_name' => $offer->seller?->company_name ?: $offer->seller?->name ?: '-',
            'supplier_profile_url' => $this->supplierProfileUrl($offer->seller),
            'company_name' => $rfq->company_name,
            'ship_name' => $rfq->ship_name,
            'imo_number' => $rfq->imo_number,
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
            'confirmed_at' => optional($awards->sortByDesc(fn (OfferAward $award) => optional($award->confirmed_at)?->getTimestamp() ?? 0)->first()?->confirmed_at)?->toISOString(),
            'currency' => $offer->currency ?: $rfq->currency ?: 'USD',
            'selected_total' => $this->decimalString($selectedTotal),
            'selected_items_count' => $rfq->request_type === 'service_request'
                ? max(1, $awards->count())
                : $selectedItems->count(),
            'total_offer_amount' => $this->decimalString($offer->total_offer_amount),
            'including_tax' => (bool) $offer->including_tax,
            'tax_amount' => $this->decimalString($offer->tax_amount),
            'including_packing' => (bool) $offer->including_packing,
            'packing_cost' => $this->decimalString($offer->packing_cost),
            'including_freight' => (bool) $offer->including_freight,
            'freight_cost' => $this->decimalString($offer->freight_cost),
            'including_mobilization' => (bool) $offer->including_mobilization,
            'mobilization_cost' => $this->decimalString($offer->mobilization_cost),
            'payment_terms_summary' => $this->paymentTermsSummary($offer),
            'order_workflow_status' => $this->orderWorkflowStatus($offer),
            'order_workflow_status_label' => $this->orderWorkflowStatusLabel($offer),
            'can_edit_order_information' => $offer->canBuyerEditOrderInformation(),
            'agreed_invoice_total' => $this->decimalString($agreedInvoiceTotal),
            'invoiced_total' => $this->decimalString($invoicedTotal),
            'remaining_invoice_total' => $this->decimalString($remainingInvoiceTotal),
            'can_manage_payment_proofs' => $serializedInvoices
                ->contains(fn (array $invoice) => (bool) ($invoice['can_upload_payment_proof'] ?? false)),
            'invoices' => $serializedInvoices->all(),
            'delivery_terms' => $offer->delivery_terms ?? '',
            'other_delivery_terms' => $offer->other_delivery_terms ?? '',
            'award_scope_policy' => $rfq->request_type === 'service_request'
                ? Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED
                : $offer->awardScopePolicy(),
            'other_payment_terms' => $offer->other_payment_terms ?? '',
            'completion_time' => $offer->completion_time ?? '',
            'offer_validity' => $offer->offer_validity ?? '',
            'general_note' => $offer->general_note ?? '',
            'service_clarification' => $offer->service_clarification ?? '',
            'buyer_note' => $awards->first()?->buyer_note ?? '',
            'request_attachments' => $this->serializeAttachments($rfq->attachments),
            'offer_attachments' => $this->serializeAttachments($offer->attachments),
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
            'selected_items' => $selectedItems,
        ];
    }

    private function supplierProfileUrl(?User $seller): ?string
    {
        $sellerId = (int) ($seller?->id ?? 0);

        if ($sellerId <= 0) {
            return null;
        }

        if (array_key_exists($sellerId, $this->supplierProfileUrls)) {
            return $this->supplierProfileUrls[$sellerId];
        }

        $listing = SupplierServiceListing::query()
            ->visible()
            ->where('seller_id', $sellerId)
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->first([
                'category_slug',
                'subcategory_slug',
                'vendor_slug',
            ]);

        $this->supplierProfileUrls[$sellerId] = $listing
            ? route('services.show', [
                'category' => $listing->category_slug,
                'subcategory' => $listing->subcategory_slug ?: $listing->category_slug,
                'vendor' => $listing->vendor_slug,
            ])
            : null;

        return $this->supplierProfileUrls[$sellerId];
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
        return Cache::remember('buyer_dashboard_active_port_counts_by_country_v1', now()->addMinutes(30), function (): array {
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
