<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\OfferItem;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AdminDashboardData
{
    protected array $supplierProfileUrls = [];

    public function __construct(
        protected OfferInvoiceData $offerInvoiceData,
        protected OfferOrderWorkflow $workflow,
        protected OfferInvoiceTotals $invoiceTotals
    ) {}

    public function dashboard(): array
    {
        return [
            'navigation' => [
                'users_url' => route('admin.dashboard', ['tab' => 'users']),
                'users_count' => User::query()->where('role', '!=', 'admin')->count(),
                'businesses_url' => route('admin.dashboard'),
                'businesses_count' => User::query()->where('role', 'seller')->count(),
                'rfqs_url' => route('admin.rfqs'),
                'rfqs_count' => Rfq::query()->count(),
                'orders_url' => route('admin.orders'),
                'orders_count' => Offer::query()
                    ->whereHas('awards', fn (Builder $query) => $query->where('status', OfferAward::STATUS_CONFIRMED))
                    ->count(),
            ],
        ];
    }

    public function rfqPage(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'latest');
        $page = max(1, (int) ($filters['page'] ?? 1));

        $query = Rfq::query()
            ->select([
                'id',
                'buyer_id',
                'reference_no',
                'company_name',
                'ship_name',
                'request_type',
                'visibility_scope',
                'country_name',
                'country_names',
                'ports_by_country',
                'requisition_date',
                'due_date',
                'currency',
                'priority',
                'status',
                'general_notes',
                'service_title',
                'service_description',
                'items_count',
                'submitted_at',
                'updated_at',
            ])
            ->withCount([
                'submittedOffers as offers_count',
                'supplierRecipients as supplier_recipients_count',
                'awards as confirmed_awards_count' => fn (Builder $query) => $query->where('status', OfferAward::STATUS_CONFIRMED),
            ]);

        if ($search !== '') {
            $normalized = mb_strtolower($search);

            $query->where(function (Builder $builder) use ($search, $normalized) {
                $like = '%'.$search.'%';

                $builder
                    ->where('reference_no', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('ship_name', 'like', $like)
                    ->orWhere('service_title', 'like', $like)
                    ->orWhere('request_type', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('visibility_scope', 'like', $like);

                if (str_contains($normalized, 'private')) {
                    $builder->orWhere('visibility_scope', Rfq::VISIBILITY_PRIVATE_SUPPLIER);
                }

                if (str_contains($normalized, 'public')) {
                    $builder->orWhere('visibility_scope', Rfq::VISIBILITY_PUBLIC_MARKETPLACE);
                }

                if (str_contains($normalized, 'service')) {
                    $builder->orWhere('request_type', 'service_request');
                }

                if (str_contains($normalized, 'spare')) {
                    $builder->orWhere('request_type', 'spare_parts');
                }
            });
        }

        match ($sort) {
            'oldest' => $query->oldest('updated_at'),
            'reference-asc' => $query->orderBy('reference_no')->orderByDesc('id'),
            'reference-desc' => $query->orderByDesc('reference_no')->orderByDesc('id'),
            'status' => $query->orderBy('status')->orderByDesc('updated_at'),
            default => $query->latest('updated_at'),
        };

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString()
            ->through(fn (Rfq $rfq) => $this->mapRfqSummary($rfq));
    }

    public function ordersPage(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'latest');
        $page = max(1, (int) ($filters['page'] ?? 1));

        $query = Offer::query()
            ->select([
                'id',
                'rfq_id',
                'seller_id',
                'request_type',
                'currency',
                'status',
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
                'updated_at',
            ])
            ->whereHas('awards', fn (Builder $query) => $query->where('status', OfferAward::STATUS_CONFIRMED))
            ->with([
                'rfq:id,reference_no,request_type,visibility_scope,company_name,ship_name,service_title,currency,priority',
                'seller:id,name,company_name',
                'items' => fn ($query) => $query->select(['id', 'offer_id', 'rfq_item_id', 'unit_price']),
                'invoices',
                'awards' => fn ($query) => $query
                    ->select(['id', 'offer_id', 'offer_item_id', 'awarded_quantity', 'confirmed_at', 'status'])
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->orderByDesc('confirmed_at'),
            ])
            ->withMax([
                'awards as latest_confirmed_at' => fn ($query) => $query->where('status', OfferAward::STATUS_CONFIRMED),
            ], 'confirmed_at');

        if ($search !== '') {
            $normalized = mb_strtolower($search);

            $query->where(function (Builder $builder) use ($search, $normalized) {
                $like = '%'.$search.'%';

                $builder
                    ->whereHas('rfq', fn (Builder $rfqQuery) => $rfqQuery
                        ->where('reference_no', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('ship_name', 'like', $like)
                        ->orWhere('service_title', 'like', $like)
                        ->orWhere('request_type', 'like', $like))
                    ->orWhereHas('seller', fn (Builder $sellerQuery) => $sellerQuery
                        ->where('company_name', 'like', $like)
                        ->orWhere('name', 'like', $like))
                    ->orWhere('request_type', 'like', $like)
                    ->orWhere('order_workflow_status', 'like', $like);

                if (str_contains($normalized, 'private')) {
                    $builder->orWhereHas('rfq', fn (Builder $rfqQuery) => $rfqQuery->where('visibility_scope', Rfq::VISIBILITY_PRIVATE_SUPPLIER));
                }

                if (str_contains($normalized, 'public')) {
                    $builder->orWhereHas('rfq', fn (Builder $rfqQuery) => $rfqQuery->where('visibility_scope', Rfq::VISIBILITY_PUBLIC_MARKETPLACE));
                }
            });
        }

        match ($sort) {
            'oldest' => $query->oldest('latest_confirmed_at'),
            'reference-asc' => $query->orderBy(
                Rfq::query()->select('reference_no')->whereColumn('rfqs.id', 'offers.rfq_id')
            ),
            'reference-desc' => $query->orderByDesc(
                Rfq::query()->select('reference_no')->whereColumn('rfqs.id', 'offers.rfq_id')
            ),
            'status' => $query->orderBy('order_workflow_status')->orderByDesc('latest_confirmed_at'),
            default => $query->orderByDesc('latest_confirmed_at'),
        };

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString()
            ->through(fn (Offer $offer) => $this->mapOrderSummary($offer));
    }

    public function rfq(Rfq $rfq): ?array
    {
        $record = Rfq::query()
            ->whereKey($rfq->id)
            ->withCount(['submittedOffers as offers_count'])
            ->with([
                'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'rfq_id',
                        'line_no',
                        'product_name',
                        'part_no',
                        'manufacturer',
                        'model_type',
                        'catalog_code',
                        'serial_number',
                        'drawing_number',
                        'quantity',
                        'unit',
                        'rob',
                        'quality',
                        'comments',
                    ])
                    ->orderBy('line_no')
                    ->with(['attachments:id,rfq_item_id,disk,path,original_name,mime_type,size']),
                'attachments:id,rfq_id,disk,path,original_name,mime_type,size',
                'supplierRecipients:id,rfq_id,company_name,category_name,subcategory_name,country_name,port_name',
            ])
            ->first();

        if (! $record) {
            return null;
        }

        $submittedOffers = Offer::query()
            ->select([
                'id',
                'rfq_id',
                'seller_id',
                'currency',
                'including_tax',
                'tax_amount',
                'including_packing',
                'packing_cost',
                'including_freight',
                'freight_cost',
                'including_mobilization',
                'mobilization_cost',
                'total_offer_amount',
                'completion_time',
                'offer_validity',
                'delivery_terms',
                'other_delivery_terms',
                'award_scope_policy',
                'payment_order_confirmation',
                'payment_before_shipment',
                'payment_invoice_days',
                'other_payment_terms',
                'service_clarification',
                'general_note',
                'submitted_at',
            ])
            ->where('rfq_id', $record->id)
            ->where('status', Offer::STATUS_SUBMITTED)
            ->with([
                'seller:id,name,company_name',
                'attachments:id,offer_id,disk,path,original_name,mime_type,size',
                'items' => fn ($query) => $query
                    ->select(['id', 'offer_id', 'rfq_item_id', 'line_no', 'offer_qty', 'unit_price', 'line_total', 'delivery_time', 'quality', 'manufacturer', 'remarks'])
                    ->orderBy('line_no')
                    ->with(['attachments:id,offer_item_id,disk,path,original_name,mime_type,size']),
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        return array_merge(
            $this->mapRfqDetail($record),
            ['offers' => $this->serializeRfqOffers($submittedOffers)]
        );
    }

    public function order(Offer $offer): ?array
    {
        $record = Offer::query()
            ->whereKey($offer->id)
            ->whereHas('awards', fn (Builder $query) => $query->where('status', OfferAward::STATUS_CONFIRMED))
            ->with([
                'rfq.attachments:id,rfq_id,disk,path,original_name,mime_type,size',
                'seller:id,name,company_name',
                'attachments:id,offer_id,disk,path,original_name,mime_type,size',
                'invoices',
                'awards' => fn ($query) => $query
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->with([
                        'offerItem' => fn ($offerItemQuery) => $offerItemQuery->with([
                            'rfqItem.attachments:id,rfq_item_id,disk,path,original_name,mime_type,size',
                            'attachments:id,offer_item_id,disk,path,original_name,mime_type,size',
                        ]),
                        'rfqItem.attachments:id,rfq_item_id,disk,path,original_name,mime_type,size',
                    ])
                    ->orderBy('id'),
            ])
            ->withMax([
                'awards as latest_confirmed_at' => fn ($query) => $query->where('status', OfferAward::STATUS_CONFIRMED),
            ], 'confirmed_at')
            ->first();

        return $record ? $this->mapOrderDetail($record) : null;
    }

    private function mapRfqSummary(Rfq $rfq): array
    {
        $status = $this->rfqStatus($rfq);
        $offersCount = $rfq->offersCount();

        return [
            'id' => $rfq->id,
            'reference_no' => $rfq->reference_no,
            'request_type' => $rfq->request_type,
            'company_name' => $rfq->company_name ?: '-',
            'ship_name' => $rfq->ship_name ?: '-',
            'visibility_scope' => $rfq->visibilityScope(),
            'is_private_request' => $rfq->isPrivateSupplierRequest(),
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'priority' => $rfq->priority,
            'country_summary' => $this->countrySummary($rfq->country_names, $rfq->country_name),
            'port_summary' => $this->portSummary($this->normalizedPortsByCountry($rfq), $rfq->port_name),
            'suppliers_count' => (int) ($rfq->supplier_recipients_count ?? 0),
            'offers_count' => $offersCount,
            'status' => $status,
            'can_edit' => $this->adminCanEditRfq($rfq),
            'can_delete' => true,
            'edit_reason' => $this->adminEditReason($rfq),
            'updated_at' => optional($rfq->updated_at)?->toISOString(),
            'compare_url' => $offersCount > 0 && $status !== 'completed'
                ? route('admin.rfqs.compare', $rfq)
                : null,
            'edit_url' => route('admin.rfqs.edit', $rfq),
            'delete_url' => route('admin.rfqs.destroy', $rfq),
            'show_url' => route('admin.rfqs.show', $rfq),
        ];
    }

    private function mapOrderSummary(Offer $offer): array
    {
        $rfq = $offer->rfq;
        $offerItemsById = $offer->items->keyBy('id');
        $awards = $offer->awards
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->values();
        $invoicesCount = $offer->invoices->count();
        $hasInvoices = $invoicesCount > 0;
        $orderWorkflowStatus = $this->workflow->resolveStatus($offer);

        $selectedTotal = $offer->request_type === 'service_request'
            ? (float) ($offer->grand_total ?? 0)
            : $awards->sum(fn (OfferAward $award) => (float) ($award->awarded_quantity ?? 0) * (float) ($offerItemsById->get($award->offer_item_id)?->unit_price ?? 0));

        return [
            'id' => $offer->id,
            'offer_id' => $offer->id,
            'reference_no' => $rfq?->reference_no ?: '-',
            'request_type' => $rfq?->request_type ?: $offer->request_type,
            'is_private_request' => $rfq?->isPrivateSupplierRequest() ?? false,
            'buyer_company' => $rfq?->company_name ?: '-',
            'supplier_name' => $offer->seller?->company_name ?: $offer->seller?->name ?: '-',
            'supplier_profile_url' => $this->supplierProfileUrl($offer->seller),
            'ship_name' => $rfq?->ship_name ?: '-',
            'confirmed_at' => $this->isoString($offer->latest_confirmed_at),
            'currency' => $offer->currency ?: $rfq?->currency ?: 'USD',
            'selected_total' => $this->decimalString($selectedTotal),
            'agreed_invoice_total' => $this->decimalString($this->invoiceTotals->agreedTotal($offer)),
            'order_workflow_status' => $orderWorkflowStatus,
            'order_workflow_status_label' => $this->workflow->label($orderWorkflowStatus),
            'invoices_count' => $invoicesCount,
            'modal_url' => route('admin.orders.modal', $offer),
            'can_edit_order_information' => ! $hasInvoices && $orderWorkflowStatus !== Offer::ORDER_STATUS_COMPLETED,
            'can_manage_invoices' => $offer->hasCompleteOrderInformation() && $orderWorkflowStatus !== Offer::ORDER_STATUS_COMPLETED,
            'can_add_invoice' => $this->invoiceTotals->canAddInvoice($offer),
            'has_invoices' => $hasInvoices,
            'show_url' => route('admin.orders.show', $offer),
            'rfq_url' => $rfq ? route('admin.rfqs.show', $rfq) : null,
        ];
    }

    private function mapRfqDetail(Rfq $rfq): array
    {
        return [
            'id' => $rfq->id,
            'request_type' => $rfq->request_type,
            'reference_no' => $rfq->reference_no,
            'company_name' => $rfq->company_name,
            'ship_name' => $rfq->ship_name,
            'country_names' => collect($rfq->country_names ?? [])->filter()->values()->all(),
            'ports_by_country' => $this->normalizedPortsByCountry($rfq),
            'port_totals_by_country' => $this->activePortCountsForCountries(collect($rfq->country_names ?? [])->filter()->values()->all()),
            'selected_categories' => $this->selectedCategoryNames($rfq),
            'selected_subcategories' => $this->selectedSubcategoryNames($rfq),
            'selected_brands' => $this->selectedBrandNames($rfq),
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'currency' => $rfq->currency,
            'priority' => $rfq->priority,
            'status' => $this->rfqStatus($rfq),
            'can_edit' => $this->adminCanEditRfq($rfq),
            'can_delete' => true,
            'edit_reason' => $this->adminEditReason($rfq),
            'compare_url' => $rfq->offersCount() > 0 && $this->rfqStatus($rfq) !== 'completed'
                ? route('admin.rfqs.compare', $rfq)
                : null,
            'edit_url' => route('admin.rfqs.edit', $rfq),
            'delete_url' => route('admin.rfqs.destroy', $rfq),
            'general_notes' => $rfq->general_notes,
            'service_title' => $rfq->service_title,
            'service_description' => $rfq->service_description,
            'items_count' => $rfq->items_count,
            'offers_count' => $rfq->offersCount(),
            'submitted_at' => optional($rfq->submitted_at)?->toISOString(),
            'updated_at' => optional($rfq->updated_at)?->toISOString(),
            'items' => $rfq->items->map(fn ($item) => [
                'id' => $item->id,
                'line_no' => $item->line_no,
                'product_name' => $item->product_name,
                'part_no' => $item->part_no,
                'manufacturer' => $item->manufacturer,
                'model_type' => $item->model_type,
                'catalog_code' => $item->catalog_code,
                'serial_number' => $item->serial_number,
                'drawing_number' => $item->drawing_number,
                'quantity' => $item->quantity !== null ? $this->decimalString($item->quantity) : null,
                'unit' => $item->unit,
                'rob' => $item->rob !== null ? $this->decimalString($item->rob) : null,
                'quality' => $item->quality,
                'comments' => $item->comments,
                'attachments' => $this->serializeAttachments($item->attachments),
            ])->values()->all(),
            'attachments' => $this->serializeAttachments($rfq->attachments),
            'recipients' => $rfq->supplierRecipients->map(fn ($recipient) => [
                'id' => $recipient->id,
                'company_name' => $recipient->company_name,
                'category_name' => $recipient->category_name,
                'subcategory_name' => $recipient->subcategory_name,
                'country_name' => $recipient->country_name,
                'port_name' => $recipient->port_name,
            ])->values()->all(),
        ];
    }

    private function mapOrderDetail(Offer $offer): array
    {
        $rfq = $offer->rfq;

        if (! $rfq) {
            return [];
        }

        $awards = $offer->awards
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->values();

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

        $serviceAward = $rfq->request_type === 'service_request'
            ? $awards->first()
            : null;
        $serializedInvoices = $offer->invoices
            ->map(fn ($invoice) => $this->offerInvoiceData->forAdmin($invoice, $offer))
            ->values();
        $agreedInvoiceTotal = $this->invoiceTotals->agreedTotal($offer);
        $invoicedTotal = $this->invoiceTotals->invoicedTotal($offer);
        $remainingInvoiceTotal = $this->invoiceTotals->remainingTotal($offer);

        return [
            'id' => $offer->id,
            'offer_id' => $offer->id,
            'rfq_id' => $rfq->id,
            'reference_no' => $rfq->reference_no,
            'show_url' => route('admin.orders.show', $offer),
            'modal_url' => route('admin.orders.modal', $offer),
            'update_order_information_url' => route('admin.orders.information.update', $offer),
            'create_invoice_url' => route('admin.orders.invoices.store', $offer),
            'rfq_show_url' => route('admin.rfqs.show', $rfq),
            'request_type' => $rfq->request_type,
            'is_private_request' => $rfq->isPrivateSupplierRequest(),
            'supplier_name' => $offer->seller?->company_name ?: $offer->seller?->name ?: '-',
            'supplier_profile_url' => $this->supplierProfileUrl($offer->seller),
            'company_name' => $rfq->company_name,
            'ship_name' => $rfq->ship_name,
            'service_title' => $rfq->service_title,
            'service_description' => $rfq->service_description ?? '',
            'country_names' => collect($rfq->country_names ?? [])->filter()->values()->all(),
            'ports_by_country' => $this->normalizedPortsByCountry($rfq),
            'port_totals_by_country' => $this->activePortCountsForCountries(collect($rfq->country_names ?? [])->filter()->values()->all()),
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'priority' => $rfq->priority,
            'general_notes' => $rfq->general_notes ?? '',
            'confirmed_at' => optional($awards->sortByDesc(fn (OfferAward $award) => optional($award->confirmed_at)?->getTimestamp() ?? 0)->first()?->confirmed_at)?->toISOString(),
            'currency' => $offer->currency ?: $rfq->currency ?: 'USD',
            'selected_total' => $this->decimalString($selectedTotal),
            'selected_items_count' => $rfq->request_type === 'service_request' ? max(1, $awards->count()) : $selectedItems->count(),
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
            'order_workflow_status' => $this->workflow->resolveStatus($offer),
            'order_workflow_status_label' => $this->workflow->label($this->workflow->resolveStatus($offer)),
            'can_edit_order_information' => $offer->canBuyerEditOrderInformation(),
            'can_manage_invoices' => $offer->canSellerManageInvoices(),
            'can_add_invoice' => $this->invoiceTotals->canAddInvoice($offer),
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
            'buyer_note' => $serviceAward?->buyer_note ?? '',
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
            'selected_items' => $selectedItems->all(),
        ];
    }

    private function serializeRfqOffers($offers): array
    {
        return collect($offers)->map(function (Offer $offer): array {
            return [
                'id' => $offer->id,
                'currency' => $offer->currency,
                'total_offer_amount' => $this->decimalString($offer->total_offer_amount),
                'including_tax' => (bool) $offer->including_tax,
                'tax_amount' => $this->decimalString($offer->tax_amount),
                'including_packing' => (bool) $offer->including_packing,
                'packing_cost' => $this->decimalString($offer->packing_cost),
                'including_freight' => (bool) $offer->including_freight,
                'freight_cost' => $this->decimalString($offer->freight_cost),
                'including_mobilization' => (bool) $offer->including_mobilization,
                'mobilization_cost' => $this->decimalString($offer->mobilization_cost),
                'completion_time' => $offer->completion_time ?? '',
                'offer_validity' => $offer->offer_validity ?? '',
                'delivery_terms' => $offer->delivery_terms ?? '',
                'other_delivery_terms' => $offer->other_delivery_terms ?? '',
                'award_scope_policy' => $offer->awardScopePolicy(),
                'payment_order_confirmation' => $this->decimalString($offer->payment_order_confirmation),
                'payment_before_shipment' => $this->decimalString($offer->payment_before_shipment),
                'payment_invoice_days' => $offer->payment_invoice_days,
                'other_payment_terms' => $offer->other_payment_terms ?? '',
                'service_clarification' => $offer->service_clarification ?? '',
                'general_note' => $offer->general_note ?? '',
                'submitted_at' => optional($offer->submitted_at)?->toISOString(),
                'seller' => [
                    'id' => $offer->seller?->id,
                    'name' => $offer->seller?->name ?? '-',
                    'company_name' => $offer->seller?->company_name ?? $offer->seller?->name ?? '-',
                ],
                'attachments' => $this->serializeAttachments($offer->attachments),
                'items' => $offer->items->map(function (OfferItem $item): array {
                    return [
                        'id' => $item->id,
                        'rfq_item_id' => $item->rfq_item_id,
                        'line_no' => $item->line_no,
                        'offer_qty' => $this->decimalString($item->offer_qty),
                        'unit_price' => $this->decimalString($item->unit_price),
                        'line_total' => $this->decimalString($item->line_total),
                        'delivery_time' => $item->delivery_time ?? '',
                        'quality' => $item->quality ?? '',
                        'manufacturer' => $item->manufacturer ?? '',
                        'remarks' => $item->remarks ?? '',
                        'attachments' => $this->serializeAttachments($item->attachments),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
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

    private function rfqStatus(Rfq $rfq): string
    {
        if ($rfq->status === Rfq::STATUS_CANCELLED) {
            return Rfq::STATUS_CANCELLED;
        }

        return $rfq->hasCompletedConfirmedOrders()
            ? 'completed'
            : ($rfq->hasConfirmedAwards()
                ? 'award_confirmed'
                : ($rfq->hasAwardSelections()
                    ? 'award_in_progress'
                    : ($rfq->effectiveStatus() === Rfq::STATUS_SUBMITTED ? 'open' : $rfq->effectiveStatus())));
    }

    private function adminCanEditRfq(Rfq $rfq): bool
    {
        return $rfq->canBeEdited();
    }

    private function adminEditReason(Rfq $rfq): ?string
    {
        if ($rfq->hasCompletedConfirmedOrders()) {
            return 'completed_orders';
        }

        if ($rfq->hasConfirmedAwards()) {
            return 'confirmed_orders';
        }

        return $rfq->editReason();
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

    private function normalizedPortsByCountry(Rfq $rfq): array
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
        return Cache::remember('admin_dashboard_active_port_counts_by_country_v1', now()->addMinutes(30), function (): array {
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

    private function selectedCategoryNames(Rfq $rfq): array
    {
        $ids = collect($rfq->category_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    private function selectedSubcategoryNames(Rfq $rfq): array
    {
        $ids = collect($rfq->subcategory_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Subcategory::query()
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    private function selectedBrandNames(Rfq $rfq): array
    {
        $ids = collect($rfq->brand_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Brand::query()
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    private function countrySummary($countryNames, ?string $fallback = null): string
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

    private function portSummary(array $portsByCountry, ?string $fallback = null): string
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
}
