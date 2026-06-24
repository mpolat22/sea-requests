<?php

namespace App\Http\Controllers;

use App\Jobs\DispatchRfqDeliveryJob;
use App\Jobs\SendRfqToSupplierJob;
use App\Models\Port;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\OfferItem;
use App\Models\Rfq;
use App\Models\RfqImportTemplate;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Support\CountryNameResolver;
use App\Support\RfqAccessService;
use App\Support\RfqImportAiRefiner;
use App\Support\RfqSpreadsheetImport;
use App\Support\RfqSupplierSuggestionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RfqController extends Controller
{
    public function __construct(
        private readonly RfqAccessService $rfqAccess,
    ) {}

    private const SERVICE_TITLE_MAX_CHARACTERS = 120;
    private const SERVICE_DESCRIPTION_MIN_CHARACTERS = 200;

    private const IMPORT_TEMPLATE_GENERAL_FIELDS = [
        'reference_no',
        'company_name',
        'ship_name',
        'imo_number',
        'country',
        'port',
        'requisition_date',
        'due_date',
        'currency',
        'priority',
        'general_notes',
    ];

    private const IMPORT_TEMPLATE_ITEM_FIELDS = [
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
    ];

    private const UNIT_OPTIONS = [
        'PCS', 'EA', 'SET', 'KIT', 'LOT', 'PAIR', 'PACK', 'BOX', 'BAG', 'ROLL',
        'MTR', 'CM', 'MM', 'KG', 'G', 'TON', 'LTR', 'ML', 'GAL', 'DRUM', 'CAN', 'TUBE',
    ];

    private const QUALITY_OPTIONS = [
        'genuine', 'oem', 'original', 'compatible', 'equivalent', 'serviceable',
        'reconditioned', 'used', 'surplus', 'alternative',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 12;

        $rfqQuery = $this->rfqAccess
            ->applyDirectoryVisibility(
                Rfq::query()->published(),
                $user
            )
            ->withCount('items')
            ->withCount(['submittedOffers as offers_count'])
            ->withCount(['awards as confirmed_awards_count' => fn ($query) => $query->where('status', OfferAward::STATUS_CONFIRMED)])
            ->with(['items:id,rfq_id,line_no,product_name'])
            ->with(['supplierRecipients:id,rfq_id,seller_id'])
            ->latest('updated_at');

        if ($user?->isSeller()) {
            $rfqQuery->with([
                'offers' => fn ($query) => $query
                    ->where('seller_id', $user->id)
                    ->select(['id', 'rfq_id', 'status'])
                    ->withCount(['awards as confirmed_awards_count' => fn ($awardQuery) => $awardQuery->where('status', OfferAward::STATUS_CONFIRMED)]),
            ]);
        }

        $rfqs = (clone $rfqQuery)
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends($request->query())
            ->through(function (Rfq $rfq) use ($user) {
                $visibility = $this->rfqAccess->visibilityPresentation($rfq, $user);
                $isLive = $rfq->canReceiveSupplierResponses();

                $itemCount = $rfq->items_count ?: $rfq->items_count_count;
                $companySeed = trim((string) ($rfq->company_name ?: $rfq->reference_no ?: 'REQ'));
                $companyMask = mb_substr($companySeed, 0, 3).'***';
                $productNames = $rfq->items
                    ->sortBy('line_no')
                    ->pluck('product_name')
                    ->filter()
                    ->values()
                    ->take(3)
                    ->all();

                $cardStatusKey = $isLive ? 'live' : 'close';
                $cardStatusCount = null;

                if ($user?->isBuyer() && (int) $rfq->buyer_id === (int) $user->id) {
                    if ($rfq->hasConfirmedAwards()) {
                        $cardStatusKey = 'award';
                    } else {
                        $cardStatusKey = 'received';
                        $cardStatusCount = $rfq->offersCount();
                    }
                } elseif ($user?->isSeller()) {
                    /** @var \App\Models\Offer|null $myOffer */
                    $myOffer = $rfq->offers->first();

                    if ($myOffer && (int) ($myOffer->confirmed_awards_count ?? 0) > 0) {
                        $cardStatusKey = 'award_confirmed';
                    } elseif ($myOffer?->status === Offer::STATUS_SUBMITTED) {
                        $cardStatusKey = 'submitted';
                    }
                }

                return [
                    'id' => $rfq->id,
                    'reference_no' => $rfq->reference_no,
                    'request_type' => $rfq->request_type,
                    'country_name' => $rfq->country_name,
                    'country_names' => collect($rfq->country_names ?? [])
                        ->filter()
                        ->values()
                        ->all(),
                    'port_name' => $rfq->port_name,
                    'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
                    'due_date' => optional($rfq->due_date)->format('Y-m-d'),
                    'priority' => $rfq->priority,
                    'status' => $isLive ? 'live' : 'close',
                    'card_status_key' => $cardStatusKey,
                    'card_status_count' => $cardStatusCount,
                    'items_count' => $itemCount,
                    'company_mask' => $companyMask,
                    'product_names' => $productNames,
                    'service_title' => $rfq->service_title,
                    'service_description' => $rfq->service_description,
                    'visibility_scope' => $visibility['scope'],
                    'is_private_request' => $visibility['is_private'],
                    'visibility_badge' => $visibility['badge'],
                    'visibility_note' => $visibility['index_note'],
                    'show_url' => $rfq->publicShowUrl(),
                    'submitted_at' => optional($rfq->submitted_at)?->toISOString(),
                    'updated_at' => optional($rfq->updated_at)?->toISOString(),
                ];
            });

        $summary = [
            'total' => (clone $rfqQuery)->count(),
            'draft' => 0,
            'submitted' => (clone $rfqQuery)->where('status', Rfq::STATUS_SUBMITTED)->count(),
            'closed' => (clone $rfqQuery)->whereIn('status', [Rfq::STATUS_CLOSED, Rfq::STATUS_CANCELLED])->count(),
        ];

        return Inertia::render('Request/RequestsIndex', [
            'requestsPage' => $rfqs,
            'requestSummary' => $summary,
            'buyerContext' => [
                'canCreate' => $user?->isBuyer() ?? false,
                'createUrl' => $user?->isBuyer() ? route('rfqs.create') : null,
            ],
            'indexUrl' => route('requests.index'),
            'meta' => [
                'title' => 'Published Requests | Sea Requests',
                'description' => 'Browse live marine spare parts RFQs and service requests, review scope summaries, and open matching opportunities on Sea Requests.',
                'canonical' => route('requests.index'),
                'robots' => 'index, follow',
                'ogImage' => asset(config('brand.assets.og_image', 'brand/sea-requests-og.png')),
                'twitterCard' => 'summary_large_image',
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->isBuyer(), 403);
        $template = $request->user()?->rfqImportTemplate;
        $supplierTarget = $this->supplierTargetFromCreateRequest($request);
        $defaults = $this->emptyRfqDefaults();
        $backUrl = route('buyer.requests');
        $actionUrl = route('rfqs.store');

        if ($supplierTarget) {
            $defaults['request_type'] = $supplierTarget['request_type'];
            $defaults['category_ids'] = $supplierTarget['category_ids'];
            $defaults['subcategory_ids'] = $supplierTarget['subcategory_ids'];
            $defaults['supplier_recipient_ids'] = $supplierTarget['candidate_listing_ids'];
            $backUrl = $supplierTarget['back_url'] ?: $backUrl;
            $actionUrl = route('rfqs.store', array_filter([
                'source' => $supplierTarget['source'] ?? null,
                'supplier' => $supplierTarget['supplier_id'] ?? null,
                'category_id' => $supplierTarget['prefill_category_id'] ?? null,
                'subcategory_id' => $supplierTarget['prefill_subcategory_id'] ?? null,
            ], fn ($value) => filled($value)));
        }

        return Inertia::render('Buyer/RFQ/Create/Page', $this->rfqFormPayload(
            $defaults,
            $actionUrl,
            $this->formatImportTemplate($template),
            'create',
            'post',
            $backUrl,
            [
                'can_edit' => true,
                'general_only' => false,
                'can_delete' => false,
                'reason' => null,
            ],
            [
                'country_options' => $supplierTarget['country_options'] ?? null,
                'ports_by_country' => $supplierTarget['ports_by_country'] ?? null,
                'supplier_target' => $supplierTarget,
            ]
        ));
    }

    public function edit(Request $request, Rfq $rfq): Response|RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canManageRfqActor($user, $rfq), 403);

        $editPolicy = $this->editPolicyForActor($rfq, $user);

        if (! $editPolicy['can_edit']) {
            return redirect()->to($this->rfqShowUrlForActor($rfq, $user))->with('error', 'rfq-edit-locked');
        }

        $rfq->load(['items.attachments', 'attachments', 'supplierRecipients']);
        $supplierTarget = $this->supplierTargetFromRfq($rfq);

        return Inertia::render('Buyer/RFQ/Create/Page', $this->rfqFormPayload(
            $this->rfqDefaultsFromModel($rfq),
            $this->rfqUpdateUrlForActor($rfq, $user),
            $this->formatImportTemplate($user?->rfqImportTemplate),
            'edit',
            'put',
            $this->rfqIndexUrlForActor($user),
            $editPolicy,
            [
                'country_options' => $supplierTarget['country_options'] ?? null,
                'ports_by_country' => $supplierTarget['ports_by_country'] ?? null,
                'supplier_target' => $supplierTarget,
            ]
        ));
    }

    public function buyerShow(Request $request, Rfq $rfq): Response
    {
        $user = $request->user();

        abort_unless($user?->isBuyer() && $rfq->buyer_id === $user->id, 403);
        $selectedOrderOfferId = $this->selectedBuyerOrderOfferId($request, $rfq);

        $rfq->loadCount(['submittedOffers as offers_count']);

        $rfq->load([
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
        ]);

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
            ->where('rfq_id', $rfq->id)
            ->where('status', Offer::STATUS_SUBMITTED)
            ->when($selectedOrderOfferId !== null, fn ($query) => $query->whereKey($selectedOrderOfferId))
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

        return Inertia::render('Buyer/RFQ/Show', [
            'backUrl' => route('buyer.requests'),
            'rfq' => array_merge(
                $this->buyerRfqPayload($rfq, $selectedOrderOfferId),
                [
                    'offers' => $this->serializeBuyerShowOffers($submittedOffers),
                ]
            ),
        ]);
    }

    public function buyerCompareShow(Request $request, Rfq $rfq): Response|RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canManageRfqActor($user, $rfq), 403);

        if ($rfq->hasCompletedConfirmedOrders()) {
            return redirect()->to($this->rfqShowUrlForActor($rfq, $user));
        }

        $rfq->loadCount(['submittedOffers as offers_count']);

        if ($rfq->request_type === 'spare_parts') {
            $rfq->load([
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
                    ->with(['attachments:id,rfq_item_id,disk,path,original_name']),
            ]);
        } else {
            $rfq->load([
                'attachments:id,rfq_id,disk,path,original_name',
            ]);
        }

        $submittedOffersQuery = Offer::query()
            ->where('rfq_id', $rfq->id)
            ->where('status', Offer::STATUS_SUBMITTED)
            ->with([
                'seller:id,name,company_name,company_logo_path',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        if ($rfq->request_type === 'spare_parts') {
            $submittedOffersQuery
                ->select([
                    'id',
                    'rfq_id',
                    'seller_id',
                    'request_type',
                    'currency',
                    'total_offer_amount',
                    'including_tax',
                    'tax_amount',
                    'including_packing',
                    'packing_cost',
                    'including_freight',
                    'freight_cost',
                    'grand_total',
                    'delivery_terms',
                    'award_scope_policy',
                    'payment_order_confirmation',
                    'payment_before_shipment',
                    'payment_invoice_days',
                    'other_payment_terms',
                    'general_note',
                ])
                ->with([
                    'items' => fn ($query) => $query
                        ->select(['id', 'offer_id', 'rfq_item_id', 'line_no', 'offer_qty', 'unit_price', 'line_total', 'delivery_time', 'quality', 'manufacturer', 'remarks'])
                        ->orderBy('line_no')
                        ->with(['attachments:id,offer_item_id,disk,path,original_name']),
                ]);
        } else {
            $submittedOffersQuery
                ->select([
                    'id',
                    'rfq_id',
                    'seller_id',
                    'request_type',
                    'currency',
                    'total_offer_amount',
                    'including_tax',
                    'tax_amount',
                    'including_mobilization',
                    'mobilization_cost',
                    'grand_total',
                    'completion_time',
                    'offer_validity',
                    'delivery_terms',
                    'award_scope_policy',
                    'payment_order_confirmation',
                    'payment_before_shipment',
                    'payment_invoice_days',
                    'other_payment_terms',
                    'service_clarification',
                    'general_note',
                ])
                ->with([
                    'attachments:id,offer_id,disk,path,original_name',
                ]);
        }

        $submittedOffers = $submittedOffersQuery->get();

        $awardRows = OfferAward::query()
            ->where('rfq_id', $rfq->id)
            ->orderBy('id')
            ->get();

        return Inertia::render('Buyer/RFQ/Compare', [
            'backUrl' => $this->rfqIndexUrlForActor($user),
            'rfq' => array_merge($this->buyerComparePayload($rfq), [
                'award_save_url' => $this->rfqAwardSaveUrlForActor($rfq, $user),
                'offers' => $this->serializeBuyerCompareOffers($submittedOffers, $awardRows, $rfq->request_type),
                'award_summary' => $this->buyerAwardSummary($rfq, $awardRows),
            ]),
        ]);
    }

    public function buyerAwardsStore(Request $request, Rfq $rfq): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canManageRfqActor($user, $rfq), 403);
        abort_if($rfq->status === Rfq::STATUS_DRAFT || $rfq->status === Rfq::STATUS_CANCELLED, 403);

        $validated = $request->validate([
            'intent' => ['required', Rule::in(['draft', 'confirm'])],
            'spare_item_awards' => ['nullable', 'array'],
            'spare_item_awards.*.offer_item_id' => ['required', 'integer'],
            'spare_item_awards.*.awarded_quantity' => ['nullable', 'numeric', 'gte:0'],
            'spare_item_awards.*.buyer_note' => ['nullable', 'string', 'max:2000'],
            'service_offer_awards' => ['nullable', 'array'],
            'service_offer_awards.*' => ['integer'],
            'service_offer_notes' => ['nullable', 'array'],
            'service_offer_notes.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $intent = $validated['intent'];
        $targetStatus = $intent === 'confirm' ? OfferAward::STATUS_CONFIRMED : OfferAward::STATUS_DRAFT;

        $submittedOffers = Offer::query()
            ->where('rfq_id', $rfq->id)
            ->where('status', Offer::STATUS_SUBMITTED)
            ->with([
                'seller:id,name,company_name,email,contact_email',
                'items:id,offer_id,rfq_item_id,offer_qty,unit_price',
            ])
            ->get();

        abort_if($submittedOffers->isEmpty(), 422, 'No submitted offers were found for this RFQ.');

        $notificationSummary = [
            'selected_suppliers' => 0,
            'selected_lines' => 0,
            'overall_total' => 0.0,
            'selected_supplier_groups' => collect(),
        ];
        $selectedOrderOfferIds = collect();

        if ($rfq->request_type === 'spare_parts') {
            $rfqItems = $rfq->items()->get(['id', 'quantity'])->keyBy('id');
            $offerItems = $submittedOffers
                ->flatMap(fn (Offer $offer) => $offer->items)
                ->keyBy('id');

            $entries = collect($validated['spare_item_awards'] ?? [])
                ->map(function (array $entry) {
                    return [
                        'offer_item_id' => (int) $entry['offer_item_id'],
                        'awarded_quantity' => round((float) ($entry['awarded_quantity'] ?? 0), 2),
                        'buyer_note' => filled(trim((string) ($entry['buyer_note'] ?? '')))
                            ? trim((string) $entry['buyer_note'])
                            : null,
                    ];
                })
                ->filter(fn (array $entry) => $entry['awarded_quantity'] > 0)
                ->values();

            $notificationSummary = [
                'selected_supplier_groups' => $entries
                    ->groupBy(function (array $entry) use ($offerItems) {
                        return optional($offerItems->get($entry['offer_item_id']))->offer_id;
                    })
                    ->map(function ($groupEntries, $offerId) use ($submittedOffers, $offerItems) {
                        /** @var Offer|null $offer */
                        $offer = $submittedOffers->firstWhere('id', (int) $offerId);
                        $seller = $offer?->seller;
                        $selectedTotal = $groupEntries->reduce(function (float $carry, array $entry) use ($offerItems): float {
                            /** @var OfferItem|null $offerItem */
                            $offerItem = $offerItems->get($entry['offer_item_id']);

                            return $carry + ($entry['awarded_quantity'] * (float) ($offerItem?->unit_price ?? 0));
                        }, 0.0);

                        return [
                            'offer_id' => (int) $offerId,
                            'seller' => $seller,
                            'selected_lines' => $groupEntries->count(),
                            'selected_total' => round($selectedTotal, 2),
                            'currency' => $offer?->currency ?: '',
                        ];
                    })
                    ->filter(fn (array $group) => $group['seller'] instanceof User)
                    ->values(),
            ];
            $notificationSummary['selected_suppliers'] = $notificationSummary['selected_supplier_groups']->count();
            $notificationSummary['selected_lines'] = $entries->count();
            $notificationSummary['overall_total'] = round((float) $notificationSummary['selected_supplier_groups']->sum('selected_total'), 2);
            $selectedOrderOfferIds = $notificationSummary['selected_supplier_groups']
                ->pluck('offer_id')
                ->filter()
                ->unique()
                ->values();

            $aggregatedByRfqItem = [];

            foreach ($entries as $entry) {
                /** @var OfferItem|null $offerItem */
                $offerItem = $offerItems->get($entry['offer_item_id']);

                if (! $offerItem) {
                    throw ValidationException::withMessages([
                        'spare_item_awards' => 'One of the selected supplier lines is no longer available.',
                    ]);
                }

                if ($entry['awarded_quantity'] > (float) $offerItem->offer_qty) {
                    throw ValidationException::withMessages([
                        'spare_item_awards' => 'Selected quantity cannot exceed the supplier offer quantity.',
                    ]);
                }

                $rfqItemId = (int) $offerItem->rfq_item_id;
                $aggregatedByRfqItem[$rfqItemId] = ($aggregatedByRfqItem[$rfqItemId] ?? 0) + $entry['awarded_quantity'];

                $requestedQty = (float) optional($rfqItems->get($rfqItemId))->quantity;
                if ($requestedQty > 0 && $aggregatedByRfqItem[$rfqItemId] > $requestedQty) {
                    throw ValidationException::withMessages([
                        'spare_item_awards' => 'Total awarded quantity cannot exceed the requested quantity for an item.',
                    ]);
                }
            }

            if ($intent === 'confirm') {
                $fullScopeSelectionError = $this->fullScopeRequiredSelectionError($submittedOffers, $entries);

                if ($fullScopeSelectionError !== null) {
                    throw ValidationException::withMessages([
                        'spare_item_awards' => $fullScopeSelectionError,
                    ]);
                }
            }

            if ($intent === 'confirm' && $entries->isEmpty()) {
                throw ValidationException::withMessages([
                    'spare_item_awards' => 'Select at least one supplier line before confirming awards.',
                ]);
            }

            $awardBuyerId = (int) ($rfq->buyer_id ?: $user->id);

            DB::transaction(function () use ($rfq, $awardBuyerId, $submittedOffers, $entries, $targetStatus, $intent): void {
                OfferAward::query()
                    ->where('rfq_id', $rfq->id)
                    ->delete();

                foreach ($entries as $entry) {
                    /** @var OfferItem $offerItem */
                    $offerItem = $submittedOffers
                        ->flatMap(fn (Offer $offer) => $offer->items)
                        ->firstWhere('id', $entry['offer_item_id']);

                    OfferAward::query()->create([
                        'rfq_id' => $rfq->id,
                        'buyer_id' => $awardBuyerId,
                        'offer_id' => $offerItem->offer_id,
                        'offer_item_id' => $offerItem->id,
                        'rfq_item_id' => $offerItem->rfq_item_id,
                        'request_type' => $rfq->request_type,
                        'status' => $targetStatus,
                        'awarded_quantity' => $entry['awarded_quantity'],
                        'buyer_note' => $entry['buyer_note'],
                        'confirmed_at' => $targetStatus === OfferAward::STATUS_CONFIRMED ? now() : null,
                    ]);
                }

                if ($intent === 'confirm' && $entries->isNotEmpty() && $rfq->status !== Rfq::STATUS_CLOSED) {
                    $rfq->forceFill(['status' => Rfq::STATUS_CLOSED])->save();
                }
            });
        } else {
            $offerIds = $submittedOffers->pluck('id')->all();
            $selectedOfferIds = collect($validated['service_offer_awards'] ?? [])
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->unique()
                ->values();
            $serviceOfferNotes = collect($validated['service_offer_notes'] ?? [])
                ->mapWithKeys(fn ($note, $offerId) => [(int) $offerId => filled(trim((string) $note)) ? trim((string) $note) : null]);

            if ($selectedOfferIds->diff($offerIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'service_offer_awards' => 'One of the selected supplier offers is no longer available.',
                ]);
            }

            if ($intent === 'confirm' && $selectedOfferIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'service_offer_awards' => 'Select at least one supplier offer before confirming awards.',
                ]);
            }

            $selectedServiceOffers = $submittedOffers
                ->whereIn('id', $selectedOfferIds->all())
                ->values();

            $notificationSummary = [
                'selected_supplier_groups' => $selectedServiceOffers
                    ->map(function (Offer $offer): array {
                        return [
                            'offer_id' => $offer->id,
                            'seller' => $offer->seller,
                            'selected_lines' => 1,
                            'selected_total' => round((float) ($offer->grand_total ?: $offer->total_offer_amount ?: 0), 2),
                            'currency' => $offer->currency ?: '',
                        ];
                    })
                    ->filter(fn (array $group) => $group['seller'] instanceof User)
                    ->values(),
            ];
            $notificationSummary['selected_suppliers'] = $notificationSummary['selected_supplier_groups']->count();
            $notificationSummary['selected_lines'] = $selectedServiceOffers->count();
            $notificationSummary['overall_total'] = round((float) $notificationSummary['selected_supplier_groups']->sum('selected_total'), 2);
            $selectedOrderOfferIds = $selectedOfferIds->values();

            $awardBuyerId = (int) ($rfq->buyer_id ?: $user->id);

            DB::transaction(function () use ($rfq, $awardBuyerId, $selectedOfferIds, $serviceOfferNotes, $targetStatus, $intent): void {
                OfferAward::query()
                    ->where('rfq_id', $rfq->id)
                    ->delete();

                foreach ($selectedOfferIds as $offerId) {
                    OfferAward::query()->create([
                        'rfq_id' => $rfq->id,
                        'buyer_id' => $awardBuyerId,
                        'offer_id' => $offerId,
                        'request_type' => $rfq->request_type,
                        'status' => $targetStatus,
                        'awarded_quantity' => null,
                        'buyer_note' => $serviceOfferNotes->get($offerId),
                        'confirmed_at' => $targetStatus === OfferAward::STATUS_CONFIRMED ? now() : null,
                    ]);
                }

                if ($intent === 'confirm' && $selectedOfferIds->isNotEmpty() && $rfq->status !== Rfq::STATUS_CLOSED) {
                    $rfq->forceFill(['status' => Rfq::STATUS_CLOSED])->save();
                }
            });
        }

        $this->notifyBuyerAboutAwardSaved(
            buyer: $this->notificationBuyerForRfq($rfq, $user),
            rfq: $rfq,
            intent: $intent,
            selectedSuppliers: (int) ($notificationSummary['selected_suppliers'] ?? 0),
            selectedLines: (int) ($notificationSummary['selected_lines'] ?? 0),
            overallTotal: (float) ($notificationSummary['overall_total'] ?? 0),
            currency: $rfq->currency ?: ($submittedOffers->first()?->currency ?: '')
        );

        $this->notifySellersAboutAwardSaved(
            rfq: $rfq,
            intent: $intent,
            selectedSupplierGroups: $notificationSummary['selected_supplier_groups'] ?? collect()
        );

        if ($intent === 'confirm' && $selectedOrderOfferIds->isNotEmpty()) {
            Offer::query()
                ->whereIn('id', $selectedOrderOfferIds->all())
                ->whereNull('order_workflow_status')
                ->update([
                    'order_workflow_status' => Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING,
                ]);
        }

        return redirect()
            ->to($this->rfqIndexUrlForActor($user))
            ->with('success', $intent === 'confirm' ? 'award-confirmed' : 'award-draft-saved');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Offer>  $submittedOffers
     * @param  \Illuminate\Support\Collection<int, array{offer_item_id:int, awarded_quantity:float, buyer_note:?string}>  $entries
     */
    private function fullScopeRequiredSelectionError(\Illuminate\Support\Collection $submittedOffers, \Illuminate\Support\Collection $entries): ?string
    {
        $selectedByOfferItemId = $entries->keyBy('offer_item_id');

        foreach ($submittedOffers as $offer) {
            if ($offer->awardScopePolicy() !== Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED) {
                continue;
            }

            $quotedItems = $offer->items
                ->filter(fn (OfferItem $item) => (float) $item->offer_qty > 0)
                ->values();

            if ($quotedItems->isEmpty()) {
                continue;
            }

            $hasAnySelectedLine = $quotedItems->contains(
                fn (OfferItem $item) => $selectedByOfferItemId->has($item->id)
            );

            if (! $hasAnySelectedLine) {
                continue;
            }

            $requiresFullScope = $quotedItems->contains(function (OfferItem $item) use ($selectedByOfferItemId): bool {
                $selectedQty = (float) ($selectedByOfferItemId->get($item->id)['awarded_quantity'] ?? 0);
                $offeredQty = (float) $item->offer_qty;

                return abs($selectedQty - $offeredQty) > 0.0001;
            });

            if (! $requiresFullScope) {
                continue;
            }

            $sellerName = $offer->seller?->company_name ?: $offer->seller?->name ?: 'This supplier';

            return "{$sellerName} requires full quoted scope acceptance. Select all quoted lines and quoted quantities from this supplier, or remove this supplier from the award.";
        }

        return null;
    }

    public function legacyShow(Request $request, Rfq $rfq): RedirectResponse
    {
        abort_unless($rfq->isPublished(), 404);
        abort_unless($rfq->isVisibleTo($request->user()), 404);

        return redirect()->to($rfq->publicShowUrl(), 301);
    }

    public function show(Request $request, Rfq $rfq, string $slug): Response|RedirectResponse
    {
        return $this->requestShowPage($request, $rfq, $slug, false);
    }

    public function sellerShow(Request $request, Rfq $rfq): Response|RedirectResponse
    {
        return $this->requestShowPage($request, $rfq, null, true);
    }

    private function requestShowPage(Request $request, Rfq $rfq, ?string $slug, bool $sellerWorkspace): Response|RedirectResponse
    {
        $user = $request->user();

        abort_unless($rfq->isPublished(), 404);
        abort_unless($rfq->isVisibleTo($user), 404);

        if ($sellerWorkspace) {
            abort_unless($user?->isSeller(), 403);
            abort_unless($this->canSellerAccessWorkspace($rfq, $user), 404);
        } elseif ($slug !== $rfq->publicSlug()) {
            return redirect()->to($rfq->publicShowUrl(), 301);
        }

        $rfq->load([
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
            'supplierRecipients:id,rfq_id,seller_id',
        ]);

        $effectiveStatus = $rfq->effectiveStatus();
        $status = $effectiveStatus === 'submitted' ? 'open' : 'close';
        $offerAccess = $this->rfqAccess->offerAccess($rfq, $user);
        $visibility = $this->rfqAccess->visibilityPresentation($rfq, $user);
        $countryNames = collect($rfq->country_names ?? [])->filter()->values()->all();
        $existingOffer = null;
        $existingOfferItems = collect();
        $existingOfferItemAwards = collect();
        $existingServiceOfferAward = null;
        $sellerRecipient = null;
        $awardConfirmedAt = null;
        $isBuyerOwner = $this->rfqAccess->isBuyerOwner($rfq, $user);
        $isSellerRecipient = $this->rfqAccess->isSellerRecipient($rfq, $user);

        if ($user?->isSeller()) {
            $sellerRecipient = $rfq->supplierRecipients()
                ->where('seller_id', $user->id)
                ->latest('id')
                ->first(['seller_id', 'country_name', 'port_name']);

            if (! $sellerRecipient && $rfq->isPublicMarketplace()) {
                $matchedListing = $this->rfqAccess->firstMatchingPublicListingForSeller($rfq, $user);
                $matchedPort = $matchedListing ? $this->matchingListingPortForRfq($matchedListing, $rfq) : null;

                if ($matchedListing) {
                    $sellerRecipient = (object) [
                        'seller_id' => $user->id,
                        'country_name' => $matchedPort
                            ? (CountryNameResolver::resolve((string) ($matchedPort->country_name ?: $matchedPort->country_code))
                                ?? $matchedPort->country_name
                                ?? $matchedPort->country_code)
                            : ($matchedListing->country ?: null),
                        'port_name' => $matchedPort?->port_name,
                    ];
                }
            }

            $existingOffer = Offer::query()
                ->where('rfq_id', $rfq->id)
                ->where('seller_id', $user->id)
                ->with([
                    'attachments:id,offer_id,disk,path,original_name,mime_type,size',
                    'items' => fn ($query) => $query
                        ->select(['id', 'offer_id', 'rfq_item_id', 'offer_qty', 'unit_price', 'line_total', 'delivery_time', 'quality', 'manufacturer', 'remarks'])
                        ->with(['attachments:id,offer_item_id,disk,path,original_name,mime_type,size']),
                ])
                ->first();

            $existingOfferItems = $existingOffer?->items->keyBy('rfq_item_id') ?? collect();
            $existingOfferItemAwards = $existingOffer
                ? OfferAward::query()
                    ->where('offer_id', $existingOffer->id)
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->whereNotNull('offer_item_id')
                    ->get(['offer_item_id', 'awarded_quantity', 'buyer_note', 'status', 'confirmed_at'])
                    ->keyBy('offer_item_id')
                : collect();
            $existingServiceOfferAward = $existingOffer
                ? OfferAward::query()
                    ->where('offer_id', $existingOffer->id)
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->whereNull('offer_item_id')
                    ->first(['buyer_note', 'status', 'confirmed_at'])
                : null;

            if (
                $existingOffer
                && ($existingServiceOfferAward !== null || $existingOfferItemAwards->isNotEmpty())
            ) {
                $offerAccess = [
                    'state' => 'awarded',
                    'notice' => 'awarded_to_you',
                    'url' => route('seller.orders.show', $existingOffer),
                ];

                $status = 'award_confirmed';
                $awardConfirmedAt = optional(
                    $existingServiceOfferAward ?: $existingOfferItemAwards
                        ->sortByDesc(fn ($award) => optional($award->confirmed_at)?->getTimestamp() ?? 0)
                        ->first()
                )->confirmed_at;
            }
        }

        if ($isBuyerOwner && $rfq->hasConfirmedAwards()) {
            $status = 'award_confirmed';
        }

        $canViewCompanyShip = $isBuyerOwner || $user?->isAdmin() || (($offerAccess['state'] ?? null) === 'awarded');
        $backUrl = $sellerWorkspace
            ? route('seller.requests')
            : ($rfq->isPrivateSupplierRequest() && $isSellerRecipient ? route('seller.requests') : route('requests.index'));

        return Inertia::render('Request/RequestShow', [
            'backUrl' => $backUrl,
            'meta' => $this->requestDetailMeta($rfq, $visibility, $sellerWorkspace, $canViewCompanyShip),
            'rfq' => [
                'id' => $rfq->id,
                'page_mode' => $sellerWorkspace ? 'seller_workspace' : 'public',
                'show_supplier_workspace' => $sellerWorkspace,
                'request_type' => $rfq->request_type,
                'reference_no' => $rfq->reference_no,
                'company_name' => $canViewCompanyShip ? $rfq->company_name : null,
                'ship_name' => $canViewCompanyShip ? $rfq->ship_name : null,
                'imo_number' => $canViewCompanyShip ? $rfq->imo_number : null,
                'can_view_company_ship' => $canViewCompanyShip,
                'is_buyer_owner' => $isBuyerOwner,
                'buyer_show_url' => $isBuyerOwner ? $rfq->buyerShowUrl() : null,
                'supplier_rfq_url' => $user?->isSeller() && $this->canSellerAccessWorkspace($rfq, $user)
                    ? route('seller.rfqs.show', $rfq)
                    : null,
                'country_names' => $countryNames,
                'ports_by_country' => $this->normalizedPortsByCountry($rfq),
                'port_totals_by_country' => $this->activePortCountsForCountries($countryNames),
                'seller_scope_country_name' => $sellerRecipient?->country_name,
                'seller_scope_port_name' => $sellerRecipient?->port_name,
                'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
                'due_date' => optional($rfq->due_date)->format('Y-m-d'),
                'currency' => $rfq->currency,
                'priority' => $rfq->priority,
                'status' => $status,
                'award_confirmed_at' => optional($awardConfirmedAt)?->toISOString(),
                'general_notes' => $rfq->general_notes,
                'service_title' => $rfq->service_title,
                'service_description' => $rfq->service_description,
                'visibility_scope' => $visibility['scope'],
                'is_private_request' => $visibility['is_private'],
                'visibility_badge' => $visibility['badge'],
                'eyebrow' => $sellerWorkspace ? 'Supplier RFQ' : $visibility['eyebrow'],
                'detail_text' => $sellerWorkspace
                    ? 'Review the RFQ scope, check your offer details, and continue the next supplier action from this screen.'
                    : $visibility['detail_text'],
                'detail_notice' => $sellerWorkspace ? null : $visibility['detail_notice'],
                'items_count' => $rfq->items_count,
                'offer_state' => $offerAccess['state'],
                'offer_notice' => $offerAccess['notice'],
                'offer_url' => $offerAccess['url'],
                'my_offer' => $existingOffer ? [
                    'id' => $existingOffer->id,
                    'status' => $existingOffer->status,
                    'currency' => $existingOffer->currency,
                    'total_offer_amount' => $this->decimalString($existingOffer->total_offer_amount),
                    'including_tax' => (bool) $existingOffer->including_tax,
                    'tax_amount' => $this->decimalString($existingOffer->tax_amount),
                    'including_packing' => (bool) $existingOffer->including_packing,
                    'packing_cost' => $this->decimalString($existingOffer->packing_cost),
                    'including_freight' => (bool) $existingOffer->including_freight,
                    'freight_cost' => $this->decimalString($existingOffer->freight_cost),
                    'including_mobilization' => (bool) $existingOffer->including_mobilization,
                    'mobilization_cost' => $this->decimalString($existingOffer->mobilization_cost),
                    'grand_total' => $this->decimalString($existingOffer->grand_total),
                    'completion_time' => $existingOffer->completion_time ?? '',
                    'offer_validity' => $existingOffer->offer_validity ?? '',
                    'delivery_terms' => $existingOffer->delivery_terms ?? '',
                    'other_delivery_terms' => $existingOffer->other_delivery_terms ?? '',
                    'payment_order_confirmation' => $this->paymentTermFieldString($existingOffer->payment_order_confirmation),
                    'payment_before_shipment' => $this->paymentTermFieldString($existingOffer->payment_before_shipment),
                    'payment_invoice_days' => $this->paymentInvoiceDaysFieldString($existingOffer->payment_invoice_days),
                    'other_payment_terms' => $existingOffer->other_payment_terms ?? '',
                    'service_clarification' => $existingOffer->service_clarification ?? '',
                    'general_note' => $existingOffer->general_note ?? '',
                    'buyer_award_note' => $existingServiceOfferAward?->buyer_note ?? '',
                    'buyer_award_status' => $existingServiceOfferAward?->status,
                    'quoted_items_count' => $existingOffer->items->count(),
                    'attachments' => $existingOffer->attachments->map(fn ($attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->original_name,
                        'url' => Storage::disk($attachment->disk)->url($attachment->path),
                        'mime_type' => $attachment->mime_type,
                        'size' => $attachment->size,
                    ])->values()->all(),
                    'updated_at' => optional($existingOffer->updated_at)?->toISOString(),
                    'submitted_at' => optional($existingOffer->submitted_at)?->toISOString(),
                ] : null,
                'submitted_at' => optional($rfq->submitted_at)?->toISOString(),
                'updated_at' => optional($rfq->updated_at)?->toISOString(),
                'items' => $rfq->items->map(function ($item) use ($existingOfferItems, $existingOfferItemAwards) {
                    $offerItem = $existingOfferItems->get($item->id);
                    $itemAward = $offerItem ? $existingOfferItemAwards->get($offerItem->id) : null;

                    return [
                        'id' => $item->id,
                        'line_no' => $item->line_no,
                        'product_name' => $item->product_name,
                        'part_no' => $item->part_no,
                        'manufacturer' => $item->manufacturer,
                        'model_type' => $item->model_type,
                        'catalog_code' => $item->catalog_code,
                        'serial_number' => $item->serial_number,
                        'drawing_number' => $item->drawing_number,
                        'quantity' => $item->quantity !== null ? rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') : null,
                        'unit' => $item->unit,
                        'rob' => $item->rob !== null ? rtrim(rtrim(number_format((float) $item->rob, 2, '.', ''), '0'), '.') : null,
                        'quality' => $item->quality,
                        'comments' => $item->comments,
                        'my_offer' => $offerItem ? [
                            'offer_qty' => $this->decimalString($offerItem->offer_qty),
                            'unit_price' => $this->decimalString($offerItem->unit_price),
                            'line_total' => $this->decimalString($offerItem->line_total),
                            'delivery_time' => $offerItem->delivery_time ?? '',
                            'quality' => $offerItem->quality ?? '',
                            'manufacturer' => $offerItem->manufacturer ?? '',
                            'remarks' => $offerItem->remarks ?? '',
                            'buyer_awarded_quantity' => $this->decimalString($itemAward?->awarded_quantity),
                            'buyer_award_note' => $itemAward?->buyer_note ?? '',
                            'buyer_award_status' => $itemAward?->status,
                            'attachments' => $offerItem->attachments->map(fn ($attachment) => [
                                'id' => $attachment->id,
                                'name' => $attachment->original_name,
                                'url' => Storage::disk($attachment->disk)->url($attachment->path),
                                'mime_type' => $attachment->mime_type,
                                'size' => $attachment->size,
                            ])->values()->all(),
                        ] : null,
                        'attachments' => $item->attachments->map(fn ($attachment) => [
                            'id' => $attachment->id,
                            'name' => $attachment->original_name,
                            'url' => Storage::disk($attachment->disk)->url($attachment->path),
                            'mime_type' => $attachment->mime_type,
                            'size' => $attachment->size,
                        ])->values()->all(),
                    ];
                })->values()->all(),
                'attachments' => $rfq->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'url' => Storage::disk($attachment->disk)->url($attachment->path),
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                ])->values()->all(),
            ],
            'similarRfqs' => [],
            'similarUrl' => route('rfqs.similar', [
                'rfq' => $rfq->id,
                'slug' => $rfq->publicSlug(),
            ]),
        ]);
    }

    private function canSellerAccessWorkspace(Rfq $rfq, User $user): bool
    {
        if (! $user->isSeller()) {
            return false;
        }

        $hasOffer = Offer::query()
            ->where('rfq_id', $rfq->id)
            ->where('seller_id', $user->id)
            ->exists();

        if ($hasOffer || $this->rfqAccess->isSellerRecipient($rfq, $user)) {
            return true;
        }

        if (! $rfq->isPublicMarketplace()) {
            return false;
        }

        return $this->rfqAccess->firstMatchingPublicListingForSeller($rfq, $user) !== null;
    }

    public function sellerOfferCreate(Request $request, Rfq $rfq): Response|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);

        $rfq->load([
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
                ->with(['attachments:id,rfq_item_id,disk,path,original_name,size']),
            'attachments:id,rfq_id,disk,path,original_name,size',
            'supplierRecipients:id,rfq_id,seller_id',
        ]);

        $offerAccess = $this->rfqAccess->offerAccess($rfq, $user);

        if (($offerAccess['state'] ?? null) !== 'eligible') {
            return redirect()->to($this->sellerOfferFallbackUrl($rfq, $user));
        }

        $countryNames = collect($rfq->country_names ?? [])->filter()->values()->all();
        $existingOffer = Offer::query()
            ->where('rfq_id', $rfq->id)
            ->where('seller_id', $user->id)
            ->with([
                'attachments:id,offer_id,disk,path,original_name,size',
                'items' => fn ($query) => $query
                    ->select(['id', 'offer_id', 'rfq_item_id', 'offer_qty', 'unit_price', 'line_total', 'delivery_time', 'quality', 'manufacturer', 'remarks'])
                    ->with(['attachments:id,offer_item_id,disk,path,original_name,size']),
            ])
            ->first();
        $existingOfferItems = $existingOffer?->items->keyBy('rfq_item_id') ?? collect();

        return Inertia::render('Supplier/OfferCreate', [
            'backUrl' => route('seller.rfqs.show', $rfq),
            'saveUrl' => route('seller.offers.store', $rfq),
            'qualityOptions' => self::QUALITY_OPTIONS,
            'offer' => [
                'id' => $existingOffer?->id,
                'status' => $existingOffer?->status,
                'including_tax' => $existingOffer?->including_tax ?? true,
                'tax_amount' => $existingOffer?->including_tax ? '' : $this->decimalString($existingOffer?->tax_amount),
                'including_mobilization' => $existingOffer?->including_mobilization ?? true,
                'mobilization_cost' => $existingOffer?->including_mobilization ? '' : $this->decimalString($existingOffer?->mobilization_cost),
                'including_packing' => $existingOffer?->including_packing ?? true,
                'packing_cost' => $existingOffer?->including_packing ? '' : $this->decimalString($existingOffer?->packing_cost),
                'including_freight' => $existingOffer?->including_freight ?? true,
                'freight_cost' => $existingOffer?->including_freight ? '' : $this->decimalString($existingOffer?->freight_cost),
                'service_total_price' => $this->decimalString($existingOffer?->total_offer_amount),
                'completion_time' => $existingOffer?->completion_time ?? '',
                'offer_validity' => $existingOffer?->offer_validity ?? '',
                'delivery_terms' => $existingOffer?->delivery_terms ?? '',
                'other_delivery_terms' => $existingOffer?->other_delivery_terms ?? '',
                'award_scope_policy' => $existingOffer?->awardScopePolicy()
                    ?? ($rfq->request_type === 'spare_parts'
                        ? Offer::AWARD_SCOPE_PARTIAL_ALLOWED
                        : Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED),
                'payment_order_confirmation' => $this->paymentTermFieldString($existingOffer?->payment_order_confirmation),
                'payment_before_shipment' => $this->paymentTermFieldString($existingOffer?->payment_before_shipment),
                'payment_invoice_days' => $this->paymentInvoiceDaysFieldString($existingOffer?->payment_invoice_days),
                'other_payment_terms' => $existingOffer?->other_payment_terms ?? '',
                'service_clarification' => $existingOffer?->service_clarification ?? '',
                'general_note' => $existingOffer?->general_note ?? '',
                'attachments' => $existingOffer?->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'size' => $attachment->size,
                ])->values()->all() ?? [],
            ],
            'rfq' => [
                'id' => $rfq->id,
                'request_type' => $rfq->request_type,
                'reference_no' => $rfq->reference_no,
                'country_names' => $countryNames,
                'ports_by_country' => $this->normalizedPortsByCountry($rfq),
                'port_totals_by_country' => $this->activePortCountsForCountries($countryNames),
                'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
                'due_date' => optional($rfq->due_date)->format('Y-m-d'),
                'currency' => $rfq->currency,
                'priority' => $rfq->priority,
                'status' => $rfq->canReceiveSupplierResponses() ? 'open' : 'close',
                'general_notes' => $rfq->general_notes,
                'service_title' => $rfq->service_title,
                'service_description' => $rfq->service_description,
                'items_count' => $rfq->items_count,
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
                    'quantity' => $item->quantity !== null ? rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') : null,
                    'unit' => $item->unit,
                    'rob' => $item->rob !== null ? rtrim(rtrim(number_format((float) $item->rob, 2, '.', ''), '0'), '.') : null,
                    'quality' => $item->quality,
                    'comments' => $item->comments,
                    'offer' => [
                        'offer_qty' => $this->decimalString(optional($existingOfferItems->get($item->id))->offer_qty),
                        'unit_price' => $this->decimalString(optional($existingOfferItems->get($item->id))->unit_price),
                        'delivery_time' => optional($existingOfferItems->get($item->id))->delivery_time ?? '',
                        'quality' => optional($existingOfferItems->get($item->id))->quality ?? '',
                        'manufacturer' => optional($existingOfferItems->get($item->id))->manufacturer ?? '',
                        'remarks' => optional($existingOfferItems->get($item->id))->remarks ?? '',
                        'attachments' => optional($existingOfferItems->get($item->id))->attachments?->map(fn ($attachment) => [
                            'id' => $attachment->id,
                            'name' => $attachment->original_name,
                            'size' => $attachment->size,
                        ])->values()->all() ?? [],
                    ],
                    'attachments' => $item->attachments->map(fn ($attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->original_name,
                        'url' => Storage::disk($attachment->disk)->url($attachment->path),
                        'size' => $attachment->size,
                    ])->values()->all(),
                ])->values()->all(),
                'attachments' => $rfq->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'url' => Storage::disk($attachment->disk)->url($attachment->path),
                    'size' => $attachment->size,
                ])->values()->all(),
            ],
        ]);
    }

    public function sellerOfferStore(Request $request, Rfq $rfq): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);

        $offerAccess = $this->rfqAccess->offerAccess($rfq, $user);

        if (($offerAccess['state'] ?? null) !== 'eligible') {
            return redirect()->to($this->sellerOfferFallbackUrl($rfq, $user));
        }

        $rfq->load(['items:id,rfq_id,line_no,quantity']);
        $isSpareParts = $rfq->request_type === 'spare_parts';

        $intent = $request->input('intent') === 'submit'
            ? Offer::STATUS_SUBMITTED
            : Offer::STATUS_DRAFT;

        $validated = $this->validateSellerOfferPayload($request, $rfq, $intent);
        $quotedItems = $isSpareParts
            ? $this->extractOfferItemsPayload($validated['items'] ?? [], $rfq)
            : [];
        $totals = $this->calculateOfferTotals($rfq, $quotedItems, $validated);

        DB::transaction(function () use ($request, $rfq, $user, $intent, $validated, $quotedItems, $totals, $isSpareParts): void {
            $this->ensureSellerRecipientSnapshotForOffer($rfq, $user);

            $offer = Offer::query()->firstOrNew([
                'rfq_id' => $rfq->id,
                'seller_id' => $user->id,
            ]);

            $awardScopePolicy = $isSpareParts
                ? ($validated['award_scope_policy']
                    ?? $offer->award_scope_policy
                    ?? Offer::AWARD_SCOPE_PARTIAL_ALLOWED)
                : Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED;
            $normalizedPaymentTerms = $this->normalizedPaymentTermsPayload($validated);

            $previousStatus = $offer->status;
            $targetStatus = $intent;

            if ($previousStatus === Offer::STATUS_SUBMITTED && $intent === Offer::STATUS_DRAFT) {
                $targetStatus = Offer::STATUS_SUBMITTED;
            }

            $offer->fill([
                'request_type' => $rfq->request_type,
                'currency' => $rfq->currency,
                'status' => $targetStatus,
                'including_tax' => (bool) $validated['including_tax'],
                'tax_amount' => $validated['including_tax'] ? 0 : $validated['tax_amount'],
                'including_mobilization' => (bool) ($validated['including_mobilization'] ?? true),
                'mobilization_cost' => ! empty($validated['including_mobilization']) ? 0 : ($validated['mobilization_cost'] ?? 0),
                'including_packing' => (bool) $validated['including_packing'],
                'packing_cost' => $validated['including_packing'] ? 0 : $validated['packing_cost'],
                'including_freight' => (bool) $validated['including_freight'],
                'freight_cost' => $validated['including_freight'] ? 0 : $validated['freight_cost'],
                'total_offer_amount' => $totals['total_offer_amount'],
                'grand_total' => $totals['grand_total'],
                'completion_time' => ($validated['completion_time'] ?? null) ?: null,
                'offer_validity' => ($validated['offer_validity'] ?? null) ?: null,
                'delivery_terms' => ($validated['delivery_terms'] ?? null) ?: null,
                'other_delivery_terms' => ($validated['other_delivery_terms'] ?? null) ?: null,
                'award_scope_policy' => $awardScopePolicy,
                'payment_order_confirmation' => $normalizedPaymentTerms['payment_order_confirmation'],
                'payment_before_shipment' => $normalizedPaymentTerms['payment_before_shipment'],
                'payment_invoice_days' => $normalizedPaymentTerms['payment_invoice_days'],
                'other_payment_terms' => $normalizedPaymentTerms['other_payment_terms'],
                'service_clarification' => ($validated['service_clarification'] ?? null) ?: null,
                'general_note' => ($validated['general_note'] ?? null) ?: null,
                'submitted_at' => $targetStatus === Offer::STATUS_SUBMITTED
                    ? ($offer->submitted_at ?? now())
                    : null,
            ]);
            $offer->save();

            if ($isSpareParts) {
                $existingItemIds = [];

                foreach ($quotedItems as $index => $quotedItem) {
                    $offerItem = OfferItem::query()->updateOrCreate(
                        [
                            'offer_id' => $offer->id,
                            'rfq_item_id' => $quotedItem['rfq_item_id'],
                        ],
                        collect($quotedItem)->except('existing_attachment_ids')->all()
                    );

                    $existingItemIds[] = $offerItem->id;

                    $retainedAttachmentIds = collect($quotedItem['existing_attachment_ids'] ?? [])
                        ->map(fn ($id) => (int) $id)
                        ->filter()
                        ->values();

                    $offerItem->attachments()
                        ->whereNotIn('id', $retainedAttachmentIds->all() ?: [0])
                        ->get()
                        ->each(function ($attachment): void {
                            Storage::disk($attachment->disk)->delete($attachment->path);
                            $attachment->delete();
                        });

                    foreach (($request->file("items.{$index}.files") ?? []) as $file) {
                        $path = $file->store("offers/{$offer->id}/items/{$offerItem->id}", 'public');

                        $offerItem->attachments()->create([
                            'disk' => 'public',
                            'path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ]);
                    }
                }

                $offer->items()
                    ->whereNotIn('id', $existingItemIds ?: [0])
                    ->get()
                    ->each(function (OfferItem $offerItem): void {
                        $offerItem->attachments->each(function ($attachment): void {
                            Storage::disk($attachment->disk)->delete($attachment->path);
                            $attachment->delete();
                        });
                        $offerItem->delete();
                    });
            } else {
                $offer->items()
                    ->get()
                    ->each(function (OfferItem $offerItem): void {
                        $offerItem->attachments->each(function ($attachment): void {
                            Storage::disk($attachment->disk)->delete($attachment->path);
                            $attachment->delete();
                        });
                        $offerItem->delete();
                    });

                $retainedAttachmentIds = collect($validated['existing_offer_attachment_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values();

                $offer->attachments()
                    ->whereNotIn('id', $retainedAttachmentIds->all() ?: [0])
                    ->get()
                    ->each(function ($attachment): void {
                        Storage::disk($attachment->disk)->delete($attachment->path);
                        $attachment->delete();
                    });

                foreach (($request->file('service_files') ?? []) as $file) {
                    $path = $file->store("offers/{$offer->id}/service", 'public');

                    $offer->attachments()->create([
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }

            if ($intent === Offer::STATUS_SUBMITTED) {
                $this->notifyBuyerAboutOfferSubmitted($rfq, $offer);
                $this->notifySellerAboutOfferSubmitted($user, $rfq, $offer, $previousStatus === Offer::STATUS_SUBMITTED);
            }
        });

        $message = $intent === Offer::STATUS_SUBMITTED ? 'offer-submitted' : 'offer-draft-saved';

        return $intent === Offer::STATUS_SUBMITTED
            ? redirect()->route('seller.requests')->with('success', $message)
            : redirect()->route('seller.offers.create', $rfq)->with('success', $message);
    }

    public function similar(Rfq $rfq, string $slug): JsonResponse
    {
        abort_unless($rfq->isPublished(), 404);
        abort_unless($rfq->isPublicMarketplace(), 404);
        abort_unless($slug === $rfq->publicSlug(), 404);

        return response()->json([
            'data' => $this->similarPublicRfqs($rfq, 10),
        ]);
    }

    public function destroy(Request $request, Rfq $rfq): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canManageRfqActor($user, $rfq), 403);

        if (! $rfq->canBeDeleted() && ! $this->isAdminRfqActor($user)) {
            return redirect()->to($this->rfqIndexUrlForActor($user))->with('error', 'rfq-delete-locked');
        }

        $rfq->load(['items.attachments', 'attachments', 'supplierRecipients']);

        DB::transaction(function () use ($rfq) {
            $this->deleteOfferTreeFiles($rfq);
            $this->deleteRfqItems($rfq);
            $this->deleteRfqAttachments($rfq);
            $rfq->supplierRecipients()->delete();
            $rfq->delete();
        });

        $this->notifyBuyerAboutRfqDeleted($this->notificationBuyerForRfq($rfq, $user), $rfq);

        return redirect()->to($this->rfqIndexUrlForActor($user))->with('success', 'rfq-deleted');
    }

    public function supplierMatches(Request $request): JsonResponse
    {
        abort_unless($this->canUseRfqFormEndpoints($request->user()), 403);

        $validated = $request->validate([
            'country_names' => ['nullable', 'array'],
            'country_names.*' => ['nullable', 'string', 'max:160'],
            'port_ids' => ['nullable', 'array'],
            'port_ids.*' => ['integer', 'exists:ports,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'exists:subcategories,id'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'candidate_ids' => ['nullable', 'array'],
            'candidate_ids.*' => ['integer', 'exists:supplier_service_listings,id'],
        ]);

        $countries = collect($validated['country_names'] ?? [])
            ->map(fn ($country) => trim((string) $country))
            ->filter()
            ->unique()
            ->values();
        $portIds = collect($validated['port_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $categoryIds = collect($validated['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $subcategoryIds = collect($validated['subcategory_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $brandIds = collect($validated['brand_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $candidateIds = collect($validated['candidate_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $selectedCountryCodes = $countries
            ->mapWithKeys(fn (string $country) => [$country => CountryNameResolver::codeForName($country)])
            ->filter()
            ->values()
            ->all();
        $selectedPorts = Port::query()
            ->active()
            ->whereIn('id', $portIds)
            ->get(['id', 'country_name', 'country_code', 'port_name', 'unlocode']);
        $selectedPortCountries = $selectedPorts
            ->map(fn (Port $port) => CountryNameResolver::resolve($port->country_name)
                ?? CountryNameResolver::resolve($port->country_code)
                ?? $port->country_name
                ?? $port->country_code)
            ->filter()
            ->unique()
            ->values();
        $storageUrl = Storage::disk('public')->url('/');

        if ($categoryIds->isNotEmpty() && $subcategoryIds->isNotEmpty()) {
            $subcategoryIds = $this->validatedSubcategoryIds($categoryIds, $subcategoryIds);
        }

        if ($countries->isEmpty()) {
            return response()->json([
                'suppliers' => [],
                'summary' => [
                    'count' => 0,
                ],
            ]);
        }

        $hasPortsForEverySelectedCountry = $countries->every(
            fn (string $country) => $selectedPortCountries->contains($country)
        );

        if (! $hasPortsForEverySelectedCountry) {
            return response()->json([
                'suppliers' => [],
                'summary' => [
                    'count' => 0,
                ],
            ]);
        }

        $suppliers = $this->matchingSupplierListings(
            countries: $countries,
            selectedPorts: $selectedPorts,
            selectedCountryCodes: $selectedCountryCodes,
            categoryIds: $categoryIds,
            subcategoryIds: $subcategoryIds,
            brandIds: $brandIds,
            candidateIds: $candidateIds,
            limit: null
        )->map(function (SupplierServiceListing $listing) use ($storageUrl) {
                $ports = $listing->ports
                    ->sortBy([
                        ['country_name', 'asc'],
                        ['port_name', 'asc'],
                    ])
                    ->map(fn ($port) => [
                        'country' => $port->country_name,
                        'name' => $port->port_name,
                        'unlocode' => $port->unlocode,
                    ])
                    ->values();

                return [
                    'id' => $listing->id,
                    'seller_id' => $listing->seller_id,
                    'company_name' => $listing->company_name,
                    'contact_name' => $listing->contact_name,
                    'country' => $listing->country,
                    'summary' => $listing->summary,
                    'category_id' => $listing->category_id,
                    'category_name' => $listing->category_name,
                    'subcategory_id' => $listing->subcategory_id,
                    'subcategory_name' => $listing->subcategory_name,
                    'vendor_slug' => $listing->vendor_slug,
                    'logo_url' => $listing->logo_path ? $storageUrl.ltrim($listing->logo_path, '/') : null,
                    'ports' => $ports->all(),
                ];
            })
            ->values();

        return response()->json([
            'suppliers' => $suppliers,
            'summary' => [
                'count' => $suppliers->count(),
            ],
        ]);
    }

    public function supplierSuggestions(Request $request, RfqSupplierSuggestionEngine $engine): JsonResponse
    {
        abort_unless($this->canUseRfqFormEndpoints($request->user()), 403);

        $validated = $request->validate([
            'request_type' => ['required', 'in:spare_parts,service_request'],
            'service_title' => ['nullable', 'string', 'max:255'],
            'service_description' => ['nullable', 'string', 'max:10000'],
            'items' => ['nullable', 'array'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.part_no' => ['nullable', 'string', 'max:255'],
            'items.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'items.*.model_type' => ['nullable', 'string', 'max:255'],
            'items.*.catalog_code' => ['nullable', 'string', 'max:255'],
            'items.*.comments' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($engine->suggest($validated));
    }

    public function ports(Request $request): JsonResponse
    {
        abort_unless($this->canUseRfqFormEndpoints($request->user()), 403);

        $countries = collect($request->input('countries', []))
            ->map(fn ($country) => trim((string) $country))
            ->filter()
            ->unique()
            ->values();

        if ($countries->isEmpty()) {
            return response()->json([
                'portsByCountry' => [],
            ]);
        }

        $countryCodes = $countries
            ->mapWithKeys(fn (string $country) => [$country => CountryNameResolver::codeForName($country)])
            ->filter()
            ->all();

        $ports = Port::query()
            ->active()
            ->where(function ($query) use ($countries, $countryCodes) {
                $query->whereIn('country_name', $countries->all());

                if ($countryCodes !== []) {
                    $query->orWhereIn('country_name', array_values($countryCodes));
                    $query->orWhereIn('country_code', array_values($countryCodes));
                }
            })
            ->orderBy('country_name')
            ->orderBy('port_name')
            ->get(['id', 'country_name', 'country_code', 'port_name', 'unlocode']);

        $portsByCountry = $ports
            ->groupBy(function (Port $port) {
                return CountryNameResolver::resolve($port->country_name)
                    ?? CountryNameResolver::resolve($port->country_code)
                    ?? $port->country_name
                    ?? $port->country_code;
            })
            ->map(fn ($countryPorts) => $countryPorts
                ->map(fn (Port $port) => [
                    'id' => $port->id,
                    'name' => $port->port_name,
                    'unlocode' => $port->unlocode,
                ])
                ->values()
                ->all())
            ->only($countries->all())
            ->toArray();

        return response()->json([
            'portsByCountry' => $portsByCountry,
        ]);
    }

    public function importPreview(
        Request $request,
        RfqSpreadsheetImport $importer,
        RfqImportAiRefiner $aiRefiner
    ): JsonResponse
    {
        abort_unless($this->canUseRfqFormEndpoints($request->user()), 403);

        try {
            $customAliases = $this->templateAliasesForUser($request->user());
            $ocrLines = [];
            $sourceRows = [];
            $effectiveAliases = $customAliases;

            if ($request->hasFile('file')) {
                $validated = $request->validate([
                    'file' => ['required', 'file', 'mimes:csv,xlsx,xls,pdf,png,jpg,jpeg,webp', 'max:15360'],
                ], [
                    'file.required' => 'Upload a PDF, image, Excel, or CSV file.',
                    'file.mimes' => 'Upload a PDF, image, CSV, XLSX, or XLS file.',
                ]);

                $extension = strtolower((string) $validated['file']->getClientOriginalExtension());

                if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'pdf'], true)) {
                    $fallbackRows = json_decode((string) $request->input('rows_payload', '[]'), true);
                    $fallbackOcrLines = json_decode((string) $request->input('ocr_lines_payload', '[]'), true);
                    $fallbackPageImages = json_decode((string) $request->input('page_images_payload', '[]'), true);
                    $fallbackRows = is_array($fallbackRows) ? $fallbackRows : [];
                    $fallbackOcrLines = is_array($fallbackOcrLines) ? $fallbackOcrLines : [];
                    $fallbackPageImages = collect(is_array($fallbackPageImages) ? $fallbackPageImages : [])
                        ->filter(fn ($image) => is_string($image) && preg_match('/^data:image\/(?:png|jpe?g|webp);base64,/i', $image))
                        ->take(3)
                        ->values()
                        ->all();

                    $detectedSheetName = (string) ($request->input('sheet_name')
                        ?: pathinfo($validated['file']->getClientOriginalName(), PATHINFO_FILENAME)
                        ?: ($extension === 'pdf' ? 'Imported PDF' : 'Imported Image'));

                    if ($extension === 'pdf' && $fallbackRows === []) {
                        $serverPdfData = $importer->extractPdfDocumentData($validated['file']);

                        if ($fallbackOcrLines === [] && ! empty($serverPdfData['ocr_lines'])) {
                            $fallbackOcrLines = is_array($serverPdfData['ocr_lines']) ? $serverPdfData['ocr_lines'] : [];
                        }

                        if ($fallbackPageImages === [] && ! empty($serverPdfData['page_images'])) {
                            $fallbackPageImages = collect($serverPdfData['page_images'])
                                ->filter(fn ($image) => is_string($image) && preg_match('/^data:image\/(?:png|jpe?g|webp);base64,/i', $image))
                                ->take(3)
                                ->values()
                                ->all();
                        }

                        if ($fallbackRows === [] && ! empty($serverPdfData['rows'])) {
                            $fallbackRows = is_array($serverPdfData['rows']) ? $serverPdfData['rows'] : [];
                        }
                    }

                    $structuredAliases = $fallbackRows !== []
                        ? $importer->prepareCustomAliasesForRows($fallbackRows, $customAliases)
                        : ['general' => [], 'items' => []];

                    if ($extension !== 'pdf') {
                        $visionPreview = $aiRefiner->extractFromImageFile(
                            $validated['file'],
                            $detectedSheetName,
                            $structuredAliases,
                            $fallbackOcrLines,
                            $fallbackRows
                        );

                        if ($visionPreview !== null) {
                            return response()->json($visionPreview);
                        }
                    }

                    if ($extension === 'pdf' && $fallbackRows === [] && $fallbackPageImages !== []) {
                        $pdfVisionPreview = $aiRefiner->extractFromDocumentImages(
                            $fallbackPageImages,
                            $validated['file']->getClientOriginalName(),
                            $detectedSheetName,
                            'pdf',
                            $structuredAliases,
                            $fallbackOcrLines,
                            $fallbackRows
                        );

                        if ($pdfVisionPreview !== null) {
                            return response()->json($pdfVisionPreview);
                        }
                    }

                    if ($fallbackRows !== []) {
                        $aiFirstPreview = $aiRefiner->extractBestPreviewFromRows(
                            $fallbackRows,
                            $detectedSheetName,
                            $validated['file']->getClientOriginalName(),
                            $extension === 'pdf' ? 'pdf' : 'image',
                            $fallbackOcrLines,
                            $structuredAliases,
                            []
                        );

                        if ($aiFirstPreview !== null && ($extension === 'pdf' || (($aiFirstPreview['summary']['items_count'] ?? 0) > 0))) {
                            if ($extension === 'pdf') {
                                return response()->json($aiFirstPreview);
                            }
                        }
                    }

                    if ($fallbackRows === [] && $fallbackOcrLines !== []) {
                        $ocrLineRows = collect($fallbackOcrLines)
                            ->map(fn ($line) => [trim((string) $line)])
                            ->filter(fn ($row) => $row[0] !== '')
                            ->values()
                            ->all();

                        if ($ocrLineRows !== []) {
                            $lineOnlyPreview = $aiRefiner->extractBestPreviewFromRows(
                                $ocrLineRows,
                                $detectedSheetName,
                                $validated['file']->getClientOriginalName(),
                                $extension === 'pdf' ? 'pdf' : 'image',
                                $fallbackOcrLines,
                                $structuredAliases,
                                []
                            );

                            if ($lineOnlyPreview !== null && ($extension === 'pdf' || (($lineOnlyPreview['summary']['items_count'] ?? 0) > 0))) {
                                return response()->json($lineOnlyPreview);
                            }
                        }
                    }

                    if ($fallbackRows === []) {
                        if ($extension === 'pdf') {
                            throw new \RuntimeException('We could not extract readable rows from this PDF.');
                        } else {
                            throw new \RuntimeException('We could not detect readable text in this image. Try a clearer image, or upload the file as PDF, Excel, or CSV.');
                        }
                    }

                    if ($fallbackRows !== []) {
                        $effectiveAliases = $structuredAliases;
                        $preview = $importer->parseRows(
                            $fallbackRows,
                            $detectedSheetName,
                            $validated['file']->getClientOriginalName(),
                            $extension === 'pdf' ? 'pdf' : 'image',
                            $effectiveAliases
                        );
                        $preview['raw']['ocr_lines'] = $fallbackOcrLines;
                        $preview['raw']['ocr_rows'] = $fallbackRows;
                        $sourceRows = $fallbackRows;
                        $ocrLines = $fallbackOcrLines;
                    }
                } else {
                    $preview = $importer->parse($validated['file'], $customAliases);
                    $sourceRows = $preview['raw']['source_rows'] ?? [];
                    $ocrLines = $preview['raw']['ocr_lines'] ?? [];
                    $effectiveAliases = $preview['raw']['applied_template_aliases'] ?? $customAliases;
                }
            } else {
                $validated = $request->validate([
                    'rows' => ['required', 'array', 'min:1'],
                    'rows.*' => ['required', 'array'],
                    'rows.*.*' => ['nullable'],
                    'ocr_lines' => ['nullable', 'array'],
                    'ocr_lines.*' => ['nullable', 'string'],
                    'file_name' => ['nullable', 'string', 'max:255'],
                    'sheet_name' => ['nullable', 'string', 'max:255'],
                    'source_type' => ['nullable', 'string', 'in:spreadsheet,pdf,image,document'],
                ], [
                    'rows.required' => 'We could not detect readable rows in this file.',
                ]);

                $effectiveAliases = $importer->prepareCustomAliasesForRows($validated['rows'], $customAliases);

                try {
                    $preview = $importer->parseRows(
                        $validated['rows'],
                        $validated['sheet_name'] ?? 'Imported Document',
                        $validated['file_name'] ?? 'Imported Document',
                        $validated['source_type'] ?? 'document',
                        $effectiveAliases
                    );

                    if (! empty($validated['ocr_lines'])) {
                        $preview['raw']['ocr_lines'] = array_values($validated['ocr_lines']);
                    }

                    $preview['raw']['ocr_rows'] = array_values($validated['rows']);
                    $sourceRows = $validated['rows'];
                    $ocrLines = $validated['ocr_lines'] ?? [];
                } catch (\Throwable $exception) {
                    $recovered = $aiRefiner->recoverFromRows(
                        $validated['rows'],
                        $validated['sheet_name'] ?? 'Imported Document',
                        $validated['file_name'] ?? 'Imported Document',
                        $validated['source_type'] ?? 'document',
                        $validated['ocr_lines'] ?? [],
                        $effectiveAliases
                    );

                    if ($recovered !== null) {
                        return response()->json($recovered);
                    }

                    $aiFirstRecovered = $aiRefiner->extractBestPreviewFromRows(
                        $validated['rows'],
                        $validated['sheet_name'] ?? 'Imported Document',
                        $validated['file_name'] ?? 'Imported Document',
                        $validated['source_type'] ?? 'document',
                        $validated['ocr_lines'] ?? [],
                        $effectiveAliases,
                        []
                    );

                    if ($aiFirstRecovered !== null) {
                        return response()->json($aiFirstRecovered);
                    }

                    $ocrLineRows = collect($validated['ocr_lines'] ?? [])
                        ->map(fn ($line) => [trim((string) $line)])
                        ->filter(fn ($row) => $row[0] !== '')
                        ->values()
                        ->all();

                    if ($ocrLineRows !== []) {
                        $lineOnlyRecovered = $aiRefiner->extractBestPreviewFromRows(
                            $ocrLineRows,
                            $validated['sheet_name'] ?? 'Imported Document',
                            $validated['file_name'] ?? 'Imported Document',
                            $validated['source_type'] ?? 'document',
                            $validated['ocr_lines'] ?? [],
                            $effectiveAliases,
                            []
                        );

                        if ($lineOnlyRecovered !== null) {
                            return response()->json($lineOnlyRecovered);
                        }
                    }

                    throw $exception;
                }
            }
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'file' => $this->friendlyImportPreviewErrorMessage($exception),
            ]);
        }

        if ($sourceRows !== []) {
            $aiFirstPreview = $aiRefiner->extractBestPreviewFromRows(
                $sourceRows,
                $preview['summary']['sheet_name'] ?? 'Imported Document',
                $preview['summary']['file_name'] ?? 'Imported Document',
                $preview['summary']['source_type'] ?? 'document',
                $ocrLines,
                $effectiveAliases,
                $preview
            );

            if ($aiFirstPreview !== null && $this->previewLooksBetter($aiFirstPreview, $preview)) {
                $preview = $aiFirstPreview;
            }
        }

        $preview = $aiRefiner->refinePreview($preview);

        return response()->json($preview);
    }

    private function friendlyImportPreviewErrorMessage(\Throwable $exception): string
    {
        $message = trim((string) $exception->getMessage());

        if ($message === '') {
            return 'We could not read this file. Please check the format and try again.';
        }

        return match ($message) {
            'PDF import helper script is missing.',
            'Python runtime for PDF import was not found.' => 'PDF import is temporarily unavailable. Please try Excel, CSV, or upload the PDF again in a moment.',
            'We could not read this PDF file.' => 'We could not read this PDF. If it is a scanned, protected, or low-quality PDF, try a clearer PDF, Excel, or CSV file.',
            'We could not extract readable rows from this PDF.' => 'We found the PDF, but could not detect readable rows. Try a cleaner export or upload the file as Excel or CSV.',
            'We could not detect the item header row in this file.' => 'We could not find the item header row. Add clear columns such as Description, Qty, Unit, Brand, or Part No and try again.',
            'We could not extract any valid item rows from this file.' => 'We found the file, but could not detect valid item rows under the header.',
            'We could not detect readable rows in this file.' => 'We could not detect readable rows in this file. Try a cleaner export or use Excel or CSV.',
            default => $message,
        };
    }

    private function previewLooksBetter(array $candidate, array $current): bool
    {
        $sourceType = (string) ($candidate['summary']['source_type'] ?? $current['summary']['source_type'] ?? 'document');
        $candidateItems = collect($candidate['items'] ?? []);
        $currentItems = collect($current['items'] ?? []);

        $candidateScore = $this->previewQualityScore($candidateItems, $sourceType);
        $currentScore = $this->previewQualityScore($currentItems, $sourceType);

        if (in_array($sourceType, ['pdf', 'image', 'document'], true)) {
            $candidateProducts = $candidateItems->filter(fn ($item) => filled(trim((string) ($item['product_name'] ?? ''))))->count();
            $currentProducts = $currentItems->filter(fn ($item) => filled(trim((string) ($item['product_name'] ?? ''))))->count();

            if (
                $currentProducts >= 3
                && $candidateProducts > 0
                && $candidateProducts <= max(1, (int) floor($currentProducts * 0.7))
                && $candidateScore < ($currentScore * 1.2)
            ) {
                return false;
            }

            if (
                ($candidate['summary']['ai_first_extracted'] ?? false)
                && $candidateItems->isNotEmpty()
                && $candidateProducts >= $currentProducts
                && $candidateScore >= ($currentScore * 0.88)
            ) {
                return true;
            }
        }

        return $candidateScore >= $currentScore;
    }

    private function previewQualityScore($items, string $sourceType): int
    {
        return $items->sum(function ($item) use ($sourceType) {
            $base = $this->itemFilledFieldCount($item);
            $product = filled(trim((string) ($item['product_name'] ?? ''))) ? 6 : 0;
            $qty = filled(trim((string) ($item['quantity'] ?? ''))) ? 3 : 0;
            $unit = filled(trim((string) ($item['unit'] ?? ''))) ? 3 : 0;
            $structured = collect(['part_no', 'manufacturer', 'model_type', 'catalog_code', 'serial_number', 'drawing_number', 'rob', 'comments'])
                ->filter(fn ($field) => filled(trim((string) ($item[$field] ?? ''))))
                ->count();

            $sourceBonus = in_array($sourceType, ['pdf', 'image', 'document'], true) ? ($structured * 2) : $structured;

            return $base + $product + $qty + $unit + $sourceBonus;
        }) + ($items->count() * 3);
    }

    private function itemFilledFieldCount(array $item): int
    {
        return collect([
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
        ])->filter(fn ($field) => filled(trim((string) ($item[$field] ?? ''))))->count();
    }

    public function saveImportTemplate(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canUseRfqFormEndpoints($user), 403);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'general' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
        ]);

        $template = RfqImportTemplate::query()->firstOrNew([
            'user_id' => $user->id,
        ]);

        $template->fill([
            'name' => $validated['name'] ?? ($template->name ?: 'My RFQ Import Template'),
            'general_aliases' => $this->sanitizeTemplateFieldAliases(
                $validated['general'] ?? [],
                self::IMPORT_TEMPLATE_GENERAL_FIELDS
            ),
            'item_aliases' => $this->sanitizeTemplateFieldAliases(
                $validated['items'] ?? [],
                self::IMPORT_TEMPLATE_ITEM_FIELDS
            ),
            'is_active' => true,
        ]);
        $template->save();

        return response()->json([
            'message' => 'Import template saved.',
            'template' => $this->formatImportTemplate($template->fresh()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isBuyer(), 403);

        $countryOptions = Port::query()
            ->active()
            ->get(['country_name'])
            ->map(fn (Port $port) => CountryNameResolver::resolve($port->country_name) ?? $port->country_name)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $isServiceRequest = $request->input('request_type') === 'service_request';

        $validated = $request->validate([
            'request_type' => ['required', 'in:spare_parts,service_request'],
            'reference_no' => ['required', 'string', 'max:120'],
            'company_name' => ['required', 'string', 'max:255'],
            'ship_name' => ['required', 'string', 'max:255'],
            'imo_number' => ['required', 'regex:/^\d{1,7}$/'],
            'country_names' => ['required', 'array', 'min:1'],
            'country_names.*' => ['required', 'string', Rule::in($countryOptions)],
            'ports_by_country' => ['required', 'array'],
            'ports_by_country.*' => ['nullable', 'array'],
            'ports_by_country.*.*' => ['integer', 'exists:ports,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'exists:subcategories,id'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'requisition_date' => ['required', 'date', 'after_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'currency' => ['required', Rule::in(['USD', 'EUR', 'CNY', 'AED'])],
            'priority' => ['required', 'in:low,normal,high,critical'],
            'status' => ['required', 'in:draft,open,closed'],
            'general_notes' => ['nullable', 'string', 'max:4000'],
            'service_title' => [
                Rule::requiredIf($isServiceRequest),
                'nullable',
                'string',
                'max:'.self::SERVICE_TITLE_MAX_CHARACTERS,
            ],
            'service_description' => [
                Rule::requiredIf($isServiceRequest),
                'nullable',
                'string',
                'max:10000',
                function (string $attribute, $value, \Closure $fail) use ($isServiceRequest) {
                    if (! $isServiceRequest || ! filled($value)) {
                        return;
                    }

                    $description = trim((string) $value);

                    if (mb_strlen($description) < self::SERVICE_DESCRIPTION_MIN_CHARACTERS) {
                        $fail('Description must be at least '.self::SERVICE_DESCRIPTION_MIN_CHARACTERS.' characters.');
                    }
                },
            ],
            'service_files' => ['nullable', 'array'],
            'service_files.*' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,xlsx,xls,csv,webp', 'max:15360'],
            'supplier_recipient_ids' => ['nullable', 'array'],
            'supplier_recipient_ids.*' => ['integer', 'exists:supplier_service_listings,id'],
            'items' => [Rule::requiredIf(! $isServiceRequest), 'nullable', 'array', 'min:1'],
            'items.*.product_name' => [Rule::requiredIf(! $isServiceRequest), 'nullable', 'string', 'max:255'],
            'items.*.part_no' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => [Rule::requiredIf(! $isServiceRequest), 'nullable', 'numeric', 'gt:0'],
            'items.*.unit' => [Rule::requiredIf(! $isServiceRequest), 'nullable', Rule::in(self::UNIT_OPTIONS)],
            'items.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'items.*.model_type' => ['nullable', 'string', 'max:255'],
            'items.*.serial_number' => ['nullable', 'string', 'max:255'],
            'items.*.catalog_code' => ['nullable', 'string', 'max:255'],
            'items.*.rob' => ['nullable', 'numeric', 'gte:0'],
            'items.*.drawing_number' => ['nullable', 'string', 'max:255'],
            'items.*.quality' => ['nullable', Rule::in(self::QUALITY_OPTIONS)],
            'items.*.comments' => ['nullable', 'string', 'max:2000'],
            'items.*.files' => ['nullable', 'array'],
            'items.*.files.*' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,xlsx,xls,csv,webp', 'max:15360'],
        ], [
            'reference_no.required' => 'Reference No is required.',
            'company_name.required' => 'Company is required.',
            'ship_name.required' => 'Ship is required.',
            'imo_number.required' => 'IMO Number is required.',
            'imo_number.regex' => 'IMO Number must contain only numbers and be no more than 7 digits.',
            'country_names.required' => 'Select at least one country.',
            'ports_by_country.required' => 'Select at least one port.',
            'requisition_date.required' => 'Requisition Date is required.',
            'requisition_date.after_or_equal' => 'Requisition Date cannot be earlier than today.',
            'due_date.required' => 'Due Date is required.',
            'due_date.after_or_equal' => 'Due Date cannot be earlier than today.',
            'currency.required' => 'Currency is required.',
            'priority.required' => 'Priority is required.',
            'service_title.required' => 'Title is required.',
            'service_description.required' => 'Description is required.',
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'items.*.product_name.required' => 'Product is required.',
            'items.*.quantity.required' => 'Qty required.',
            'items.*.quantity.numeric' => 'Qty must be a number.',
            'items.*.quantity.gt' => 'Qty must be greater than 0.',
            'items.*.unit.required' => 'Unit required.',
        ]);

        $selectedCountries = collect($validated['country_names'])
            ->map(fn ($country) => trim((string) $country))
            ->filter()
            ->values();

        $selectedPortIdsByCountry = collect($validated['ports_by_country'] ?? [])
            ->map(fn ($ids) => collect($ids)->map(fn ($id) => (int) $id)->unique()->values());

        foreach ($selectedCountries as $country) {
            if (($selectedPortIdsByCountry->get($country)?->isNotEmpty()) !== true) {
                throw ValidationException::withMessages([
                    'ports_by_country' => 'Select at least one port for each selected country.',
                ]);
            }
        }

        $selectedPortIds = $selectedPortIdsByCountry
            ->only($selectedCountries->all())
            ->flatten()
            ->unique()
            ->values();

        $selectedRecipientIds = collect($validated['supplier_recipient_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $selectedPorts = Port::query()
            ->active()
            ->whereIn('id', $selectedPortIds)
            ->get(['id', 'country_name', 'port_name', 'unlocode'])
            ->keyBy('id');

        $storedPortsByCountry = $selectedCountries
            ->mapWithKeys(function (string $country) use ($selectedPortIdsByCountry, $selectedPorts) {
                $ports = $selectedPortIdsByCountry
                    ->get($country, collect())
                    ->map(function (int $portId) use ($country, $selectedPorts) {
                        $port = $selectedPorts->get($portId);
                        $portCountry = $port ? (CountryNameResolver::resolve($port->country_name) ?? $port->country_name) : null;

                        if (! $port || $portCountry !== $country) {
                            return null;
                        }

                        return [
                            'id' => $port->id,
                            'name' => $port->port_name,
                            'unlocode' => $port->unlocode,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [$country => $ports];
            })
            ->all();

        $countrySummary = $selectedCountries->implode(', ');
        $allSelectedPortNames = collect($storedPortsByCountry)
            ->flatten(1)
            ->pluck('name')
            ->values();
        $portSummary = $allSelectedPortNames->take(3)->implode(', ');

        if ($allSelectedPortNames->count() > 3) {
            $portSummary .= ' +'.($allSelectedPortNames->count() - 3).' more';
        }

        $selectedCategoryIds = collect($validated['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $selectedSubcategoryIds = collect($validated['subcategory_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedCategoryIds->isNotEmpty() && $selectedSubcategoryIds->isNotEmpty()) {
            $selectedSubcategoryIds = $this->validatedSubcategoryIds($selectedCategoryIds, $selectedSubcategoryIds);
        }

        $selectedBrandIds = collect($validated['brand_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $selectedCountryCodes = $selectedCountries
            ->mapWithKeys(fn (string $country) => [$country => CountryNameResolver::codeForName($country)])
            ->filter()
            ->values()
            ->all();

        $selectedRecipients = $this->matchingSupplierListings(
            countries: $selectedCountries,
            selectedPorts: $selectedPorts->values(),
            selectedCountryCodes: $selectedCountryCodes,
            categoryIds: $selectedCategoryIds,
            subcategoryIds: $selectedSubcategoryIds,
            brandIds: $selectedBrandIds,
            candidateIds: $selectedRecipientIds,
            limit: null
        );
        $visibilityScope = $this->determineVisibilityScope(
            $validated['request_type'],
            $selectedRecipients,
            $this->supplierTargetFromCreateRequest($request) !== null
        );

        $storedStatus = match ($validated['status']) {
            'open' => 'submitted',
            'closed' => 'closed',
            default => 'draft',
        };

        if ($storedStatus === 'submitted'
            && $visibilityScope === Rfq::VISIBILITY_PRIVATE_SUPPLIER
            && $selectedRecipients->isEmpty()) {
            throw ValidationException::withMessages([
                'supplier_recipient_ids' => 'No approved suppliers match this private request scope.',
            ]);
        }

        $rfq = DB::transaction(function () use ($validated, $user, $request, $selectedCountries, $storedPortsByCountry, $countrySummary, $portSummary, $selectedRecipients, $selectedCategoryIds, $selectedSubcategoryIds, $selectedBrandIds, $visibilityScope, $storedStatus) {
            $rfq = Rfq::query()->create([
                'buyer_id' => $user->id,
                'request_type' => $validated['request_type'],
                'visibility_scope' => $visibilityScope,
                'reference_no' => trim((string) $validated['reference_no']),
                'company_name' => trim((string) $validated['company_name']),
                'ship_name' => trim((string) $validated['ship_name']),
                'imo_number' => trim((string) $validated['imo_number']),
                'country_name' => $countrySummary,
                'port_name' => $portSummary,
                'country_names' => $selectedCountries->all(),
                'ports_by_country' => $storedPortsByCountry,
                'category_ids' => $selectedCategoryIds->all(),
                'subcategory_ids' => $selectedSubcategoryIds->all(),
                'brand_ids' => $selectedBrandIds->all(),
                'requisition_date' => $validated['requisition_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => strtoupper(trim((string) $validated['currency'])),
                'priority' => $validated['priority'],
                'status' => $storedStatus,
                'general_notes' => filled($validated['general_notes'] ?? null) ? trim((string) $validated['general_notes']) : null,
                'service_title' => filled($validated['service_title'] ?? null) ? trim((string) $validated['service_title']) : null,
                'service_description' => filled($validated['service_description'] ?? null) ? trim((string) $validated['service_description']) : null,
                'items_count' => $validated['request_type'] === 'service_request' ? 1 : count($validated['items'] ?? []),
                'submitted_at' => $storedStatus !== 'draft' ? now() : null,
            ]);

            if ($validated['request_type'] === 'spare_parts') {
                foreach ($validated['items'] as $index => $itemData) {
                    $item = $rfq->items()->create([
                        'line_no' => $index + 1,
                        'product_name' => trim((string) $itemData['product_name']),
                        'part_no' => filled($itemData['part_no'] ?? null) ? trim((string) $itemData['part_no']) : null,
                        'quantity' => $itemData['quantity'],
                        'unit' => strtoupper(trim((string) $itemData['unit'])),
                        'manufacturer' => filled($itemData['manufacturer'] ?? null) ? trim((string) $itemData['manufacturer']) : null,
                        'model_type' => filled($itemData['model_type'] ?? null) ? trim((string) $itemData['model_type']) : null,
                        'serial_number' => filled($itemData['serial_number'] ?? null) ? trim((string) $itemData['serial_number']) : null,
                        'catalog_code' => filled($itemData['catalog_code'] ?? null) ? trim((string) $itemData['catalog_code']) : null,
                        'rob' => filled($itemData['rob'] ?? null) ? $itemData['rob'] : null,
                        'drawing_number' => filled($itemData['drawing_number'] ?? null) ? trim((string) $itemData['drawing_number']) : null,
                        'quality' => $itemData['quality'],
                        'comments' => filled($itemData['comments'] ?? null) ? trim((string) $itemData['comments']) : null,
                    ]);

                    foreach (($request->file("items.{$index}.files") ?? []) as $file) {
                        $path = $file->store("rfqs/{$rfq->id}/items/{$item->id}", 'public');

                        $item->attachments()->create([
                            'disk' => 'public',
                            'path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ]);
                    }
                }
            } else {
                foreach (($request->file('service_files') ?? []) as $file) {
                    $path = $file->store("rfqs/{$rfq->id}/attachments", 'public');

                    $rfq->attachments()->create([
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }

            foreach ($selectedRecipients as $recipient) {
                $primaryPort = $recipient->ports->first();

                $rfq->supplierRecipients()->create([
                    'supplier_service_listing_id' => $recipient->id,
                    'seller_id' => $recipient->seller_id,
                    'company_name' => $recipient->company_name,
                    'category_name' => $recipient->category_name,
                    'subcategory_name' => $recipient->subcategory_name,
                    'country_name' => $recipient->country ?: $primaryPort?->country_name,
                    'port_name' => $primaryPort?->port_name,
                ]);
            }

            return $rfq;
        });

        if ($rfq->status === 'submitted' && $rfq->supplierRecipients()->exists()) {
            DispatchRfqDeliveryJob::dispatch($rfq->id);
        }

        $this->notifyBuyerAboutRfqCreated($user, $rfq);

        return redirect()->route('buyer.requests')->with('success', 'rfq-created');
    }

    public function update(Request $request, Rfq $rfq): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canManageRfqActor($user, $rfq), 403);

        $editPolicy = $this->editPolicyForActor($rfq, $user);

        if (! $editPolicy['can_edit']) {
            return redirect()->to($this->rfqShowUrlForActor($rfq, $user))->with('error', 'rfq-edit-locked');
        }

        $countryOptions = $this->rfqCountryOptions();
        $isGeneralOnlyEdit = $editPolicy['general_only'];
        $requestTypeLockedForUpdate = $isGeneralOnlyEdit
            || ($this->isAdminRfqActor($user) && ($rfq->hasOffers() || $rfq->hasAwardSelections()));
        $effectiveRequestType = $requestTypeLockedForUpdate ? $rfq->request_type : $request->input('request_type');
        $isServiceRequest = $effectiveRequestType === 'service_request';
        $dueDateRules = $this->isAdminRfqActor($user)
            ? ['required', 'date']
            : ['required', 'date', 'after_or_equal:today'];

        $validated = $request->validate([
            'request_type' => ['required', Rule::in($requestTypeLockedForUpdate ? [$rfq->request_type] : ['spare_parts', 'service_request'])],
            'reference_no' => ['required', 'string', 'max:120'],
            'company_name' => ['required', 'string', 'max:255'],
            'ship_name' => ['required', 'string', 'max:255'],
            'imo_number' => ['required', 'regex:/^\d{1,7}$/'],
            'country_names' => ['required', 'array', 'min:1'],
            'country_names.*' => ['required', 'string', Rule::in($countryOptions)],
            'ports_by_country' => ['required', 'array'],
            'ports_by_country.*' => ['nullable', 'array'],
            'ports_by_country.*.*' => ['integer', 'exists:ports,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'exists:subcategories,id'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'requisition_date' => ['required', 'date'],
            'due_date' => $dueDateRules,
            'currency' => ['required', Rule::in(['USD', 'EUR', 'CNY', 'AED'])],
            'priority' => ['required', 'in:low,normal,high,critical'],
            'status' => ['required', 'in:draft,open,closed'],
            'general_notes' => ['nullable', 'string', 'max:4000'],
            'service_title' => [
                Rule::requiredIf($isServiceRequest),
                'nullable',
                'string',
                'max:'.self::SERVICE_TITLE_MAX_CHARACTERS,
            ],
            'service_description' => [
                Rule::requiredIf($isServiceRequest),
                'nullable',
                'string',
                'max:10000',
                function (string $attribute, $value, \Closure $fail) use ($isServiceRequest) {
                    if (! $isServiceRequest || ! filled($value)) {
                        return;
                    }

                    $description = trim((string) $value);

                    if (mb_strlen($description) < self::SERVICE_DESCRIPTION_MIN_CHARACTERS) {
                        $fail('Description must be at least '.self::SERVICE_DESCRIPTION_MIN_CHARACTERS.' characters.');
                    }
                },
            ],
            'service_files' => ['nullable', 'array'],
            'service_files.*' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,xlsx,xls,csv,webp', 'max:15360'],
            'existing_service_attachment_ids' => ['nullable', 'array'],
            'existing_service_attachment_ids.*' => ['integer'],
            'supplier_recipient_ids' => ['nullable', 'array'],
            'supplier_recipient_ids.*' => ['integer', 'exists:supplier_service_listings,id'],
            'items' => [Rule::requiredIf(! $isServiceRequest), 'nullable', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.product_name' => [Rule::requiredIf(! $isServiceRequest), 'nullable', 'string', 'max:255'],
            'items.*.part_no' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => [Rule::requiredIf(! $isServiceRequest), 'nullable', 'numeric', 'gt:0'],
            'items.*.unit' => [Rule::requiredIf(! $isServiceRequest), 'nullable', Rule::in(self::UNIT_OPTIONS)],
            'items.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'items.*.model_type' => ['nullable', 'string', 'max:255'],
            'items.*.serial_number' => ['nullable', 'string', 'max:255'],
            'items.*.catalog_code' => ['nullable', 'string', 'max:255'],
            'items.*.rob' => ['nullable', 'numeric', 'gte:0'],
            'items.*.drawing_number' => ['nullable', 'string', 'max:255'],
            'items.*.quality' => ['nullable', Rule::in(self::QUALITY_OPTIONS)],
            'items.*.comments' => ['nullable', 'string', 'max:2000'],
            'items.*.files' => ['nullable', 'array'],
            'items.*.files.*' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,xlsx,xls,csv,webp', 'max:15360'],
            'items.*.existing_attachment_ids' => ['nullable', 'array'],
            'items.*.existing_attachment_ids.*' => ['integer'],
        ], [
            'reference_no.required' => 'Reference No is required.',
            'company_name.required' => 'Company is required.',
            'ship_name.required' => 'Ship is required.',
            'imo_number.required' => 'IMO Number is required.',
            'imo_number.regex' => 'IMO Number must contain only numbers and be no more than 7 digits.',
            'country_names.required' => 'Select at least one country.',
            'ports_by_country.required' => 'Select at least one port.',
            'requisition_date.required' => 'Requisition Date is required.',
            'due_date.required' => 'Due Date is required.',
            'due_date.after_or_equal' => 'Due Date cannot be earlier than today.',
            'currency.required' => 'Currency is required.',
            'priority.required' => 'Priority is required.',
            'service_title.required' => 'Title is required.',
            'service_description.required' => 'Description is required.',
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'items.*.product_name.required' => 'Product is required.',
            'items.*.quantity.required' => 'Qty required.',
            'items.*.quantity.numeric' => 'Qty must be a number.',
            'items.*.quantity.gt' => 'Qty must be greater than 0.',
            'items.*.unit.required' => 'Unit required.',
        ]);

        $selectedCountries = collect($validated['country_names'])
            ->map(fn ($country) => trim((string) $country))
            ->filter()
            ->values();

        $selectedPortIdsByCountry = collect($validated['ports_by_country'] ?? [])
            ->map(fn ($ids) => collect($ids)->map(fn ($id) => (int) $id)->unique()->values());

        foreach ($selectedCountries as $country) {
            if (($selectedPortIdsByCountry->get($country)?->isNotEmpty()) !== true) {
                throw ValidationException::withMessages([
                    'ports_by_country' => 'Select at least one port for each selected country.',
                ]);
            }
        }

        $selectedPortIds = $selectedPortIdsByCountry
            ->only($selectedCountries->all())
            ->flatten()
            ->unique()
            ->values();

        $selectedPorts = Port::query()
            ->active()
            ->whereIn('id', $selectedPortIds)
            ->get(['id', 'country_name', 'port_name', 'unlocode'])
            ->keyBy('id');

        $storedPortsByCountry = $selectedCountries
            ->mapWithKeys(function (string $country) use ($selectedPortIdsByCountry, $selectedPorts) {
                $ports = $selectedPortIdsByCountry
                    ->get($country, collect())
                    ->map(function (int $portId) use ($country, $selectedPorts) {
                        $port = $selectedPorts->get($portId);
                        $portCountry = $port ? (CountryNameResolver::resolve($port->country_name) ?? $port->country_name) : null;

                        if (! $port || $portCountry !== $country) {
                            return null;
                        }

                        return [
                            'id' => $port->id,
                            'name' => $port->port_name,
                            'unlocode' => $port->unlocode,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [$country => $ports];
            })
            ->all();

        $countrySummary = $selectedCountries->implode(', ');
        $allSelectedPortNames = collect($storedPortsByCountry)
            ->flatten(1)
            ->pluck('name')
            ->values();
        $portSummary = $allSelectedPortNames->take(3)->implode(', ');

        if ($allSelectedPortNames->count() > 3) {
            $portSummary .= ' +'.($allSelectedPortNames->count() - 3).' more';
        }

        $selectedRecipientIds = collect($validated['supplier_recipient_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $selectedCategoryIds = collect($validated['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $selectedSubcategoryIds = collect($validated['subcategory_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedCategoryIds->isNotEmpty() && $selectedSubcategoryIds->isNotEmpty()) {
            $selectedSubcategoryIds = $this->validatedSubcategoryIds($selectedCategoryIds, $selectedSubcategoryIds);
        }

        $selectedBrandIds = collect($validated['brand_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $selectedCountryCodes = $selectedCountries
            ->mapWithKeys(fn (string $country) => [$country => CountryNameResolver::codeForName($country)])
            ->filter()
            ->values()
            ->all();

        $selectedRecipients = $this->matchingSupplierListings(
            countries: $selectedCountries,
            selectedPorts: $selectedPorts->values(),
            selectedCountryCodes: $selectedCountryCodes,
            categoryIds: $selectedCategoryIds,
            subcategoryIds: $selectedSubcategoryIds,
            brandIds: $selectedBrandIds,
            candidateIds: $selectedRecipientIds,
            limit: null
        );
        $visibilityScope = $rfq->visibilityScope();

        $previousStatus = $rfq->status;
        $newStatus = $validated['status'] === 'open'
            ? 'submitted'
            : ($validated['status'] === 'closed' ? 'closed' : 'draft');

        if (! $isGeneralOnlyEdit
            && $newStatus === 'submitted'
            && $visibilityScope === Rfq::VISIBILITY_PRIVATE_SUPPLIER
            && $selectedRecipients->isEmpty()) {
            throw ValidationException::withMessages([
                'supplier_recipient_ids' => 'No approved suppliers match this private request scope.',
            ]);
        }

        $dispatchAllRecipients = false;
        $newRecipientIds = [];

        DB::transaction(function () use ($rfq, $request, $validated, $selectedCountries, $storedPortsByCountry, $countrySummary, $portSummary, $selectedRecipients, $selectedCategoryIds, $selectedSubcategoryIds, $selectedBrandIds, $visibilityScope, $newStatus, $previousStatus, $isGeneralOnlyEdit, &$dispatchAllRecipients, &$newRecipientIds) {
            $rfq->forceFill([
                'request_type' => $isGeneralOnlyEdit ? $rfq->request_type : $validated['request_type'],
                'visibility_scope' => $visibilityScope,
                'reference_no' => trim((string) $validated['reference_no']),
                'company_name' => trim((string) $validated['company_name']),
                'ship_name' => trim((string) $validated['ship_name']),
                'imo_number' => trim((string) $validated['imo_number']),
                'country_name' => $countrySummary,
                'port_name' => $portSummary,
                'country_names' => $selectedCountries->all(),
                'ports_by_country' => $storedPortsByCountry,
                'category_ids' => $isGeneralOnlyEdit ? ($rfq->category_ids ?? []) : $selectedCategoryIds->all(),
                'subcategory_ids' => $isGeneralOnlyEdit ? ($rfq->subcategory_ids ?? []) : $selectedSubcategoryIds->all(),
                'brand_ids' => $isGeneralOnlyEdit ? ($rfq->brand_ids ?? []) : $selectedBrandIds->all(),
                'requisition_date' => $validated['requisition_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => strtoupper(trim((string) $validated['currency'])),
                'priority' => $validated['priority'],
                'status' => $newStatus,
                'general_notes' => filled($validated['general_notes'] ?? null) ? trim((string) $validated['general_notes']) : null,
                'service_title' => $isGeneralOnlyEdit
                    ? $rfq->service_title
                    : (filled($validated['service_title'] ?? null) ? trim((string) $validated['service_title']) : null),
                'service_description' => $isGeneralOnlyEdit
                    ? $rfq->service_description
                    : (filled($validated['service_description'] ?? null) ? trim((string) $validated['service_description']) : null),
                'items_count' => $isGeneralOnlyEdit
                    ? $rfq->items_count
                    : ($validated['request_type'] === 'service_request' ? 1 : count($validated['items'] ?? [])),
                'submitted_at' => $newStatus !== 'draft'
                    ? ($rfq->submitted_at ?: now())
                    : null,
            ])->save();

            if (! $isGeneralOnlyEdit) {
                if ($validated['request_type'] === 'spare_parts') {
                    $this->syncRfqItems($rfq, $validated['items'] ?? [], $request);
                    $this->deleteRfqAttachments($rfq);
                } else {
                    $this->deleteRfqItems($rfq);
                    $retainedAttachmentIds = collect($validated['existing_service_attachment_ids'] ?? [])
                        ->map(fn ($id) => (int) $id)
                        ->filter()
                        ->unique()
                        ->values();

                    $rfq->attachments()
                        ->whereNotIn('id', $retainedAttachmentIds->all() ?: [0])
                        ->get()
                        ->each(function ($attachment): void {
                            Storage::disk($attachment->disk)->delete($attachment->path);
                            $attachment->delete();
                        });

                    foreach (($request->file('service_files') ?? []) as $file) {
                        $path = $file->store("rfqs/{$rfq->id}/attachments", 'public');

                        $rfq->attachments()->create([
                            'disk' => 'public',
                            'path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ]);
                    }
                }

                $newRecipientIds = $this->syncSupplierRecipients($rfq, $selectedRecipients);
                $dispatchAllRecipients = $newStatus === 'submitted' && $previousStatus !== 'submitted' && $rfq->supplierRecipients()->exists();
            }
        });

        if ($dispatchAllRecipients) {
            DispatchRfqDeliveryJob::dispatch($rfq->id);
        } elseif ($newStatus === 'submitted' && $newRecipientIds !== []) {
            foreach ($newRecipientIds as $recipientId) {
                $recipient = $rfq->supplierRecipients()->find($recipientId);

                if (! $recipient) {
                    continue;
                }

                $recipient->forceFill([
                    'delivery_status' => 'queued',
                    'queued_at' => now(),
                    'delivery_error' => null,
                ])->save();

                SendRfqToSupplierJob::dispatch($recipient->id);
            }
        }

        $this->notifyBuyerAboutRfqUpdated($this->notificationBuyerForRfq($rfq, $user), $rfq);

        return redirect()->to($this->rfqIndexUrlForActor($user))->with('success', 'rfq-updated');
    }

    private function canUseRfqFormEndpoints(?User $user): bool
    {
        return $user?->isBuyer() || $user?->isAdmin();
    }

    private function canManageRfqActor(?User $user, Rfq $rfq): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isBuyer() && $rfq->buyer_id === $user->id;
    }

    private function isAdminRfqActor(?User $user): bool
    {
        return (bool) ($user?->isAdmin());
    }

    private function rfqIndexUrlForActor(?User $user): string
    {
        return $this->isAdminRfqActor($user)
            ? route('admin.rfqs')
            : route('buyer.requests');
    }

    private function rfqShowUrlForActor(Rfq $rfq, ?User $user): string
    {
        return $this->isAdminRfqActor($user)
            ? route('admin.rfqs.show', $rfq)
            : $rfq->buyerShowUrl();
    }

    private function rfqUpdateUrlForActor(Rfq $rfq, ?User $user): string
    {
        return $this->isAdminRfqActor($user)
            ? route('admin.rfqs.update', $rfq)
            : route('rfqs.update', $rfq);
    }

    private function rfqAwardSaveUrlForActor(Rfq $rfq, ?User $user): string
    {
        return $this->isAdminRfqActor($user)
            ? route('admin.rfqs.awards.store', $rfq)
            : route('buyer.rfqs.awards.store', $rfq);
    }

    private function editPolicyForActor(Rfq $rfq, ?User $user): array
    {
        if ($this->isAdminRfqActor($user)) {
            if ($rfq->hasCompletedConfirmedOrders()) {
                return [
                    'can_edit' => false,
                    'general_only' => false,
                    'can_delete' => true,
                    'reason' => 'completed_orders',
                ];
            }

            if ($rfq->hasConfirmedAwards()) {
                return [
                    'can_edit' => false,
                    'general_only' => false,
                    'can_delete' => true,
                    'reason' => 'confirmed_orders',
                ];
            }

            $policy = $this->editPolicyForRfq($rfq);

            return [
                'can_edit' => $policy['can_edit'],
                'general_only' => $policy['general_only'],
                'can_delete' => true,
                'reason' => $policy['reason'],
            ];
        }

        return $this->editPolicyForRfq($rfq);
    }

    private function notificationBuyerForRfq(Rfq $rfq, User $actor): User
    {
        return $this->isAdminRfqActor($actor)
            ? ($rfq->buyer ?: $actor)
            : $actor;
    }

    private function emptyRfqDefaults(): array
    {
        return [
            'request_type' => 'spare_parts',
            'reference_no' => 'RFQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'company_name' => '',
            'ship_name' => '',
            'imo_number' => '',
            'country_names' => [],
            'ports_by_country' => [],
            'category_ids' => [],
            'subcategory_ids' => [],
            'brand_ids' => [],
            'requisition_date' => '',
            'due_date' => '',
            'currency' => '',
            'priority' => '',
            'status' => 'open',
            'general_notes' => '',
            'service_title' => '',
            'service_description' => '',
            'service_files' => [],
            'supplier_recipient_ids' => [],
            'items' => [
                [
                    'id' => null,
                    'product_name' => '',
                    'part_no' => '',
                    'quantity' => '',
                    'unit' => '',
                    'manufacturer' => '',
                    'model_type' => '',
                    'serial_number' => '',
                    'catalog_code' => '',
                    'rob' => '',
                    'drawing_number' => '',
                    'quality' => '',
                    'comments' => '',
                    'files' => [],
                ],
            ],
        ];
    }

    private function rfqDefaultsFromModel(Rfq $rfq): array
    {
        return [
            'request_type' => $rfq->request_type,
            'reference_no' => $rfq->reference_no,
            'company_name' => $rfq->company_name,
            'ship_name' => $rfq->ship_name,
            'imo_number' => $rfq->imo_number ?? '',
            'country_names' => collect($rfq->country_names ?? [])->filter()->values()->all(),
            'ports_by_country' => $this->normalizedPortIdsByCountry($rfq),
            'category_ids' => collect($rfq->category_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all(),
            'subcategory_ids' => collect($rfq->subcategory_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all(),
            'brand_ids' => collect($rfq->brand_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all(),
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'currency' => $rfq->currency,
            'priority' => $rfq->priority,
            'status' => $rfq->effectiveStatus() === 'submitted' ? 'open' : $rfq->effectiveStatus(),
            'general_notes' => $rfq->general_notes ?? '',
            'service_title' => $rfq->service_title ?? '',
            'service_description' => $rfq->service_description ?? '',
            'service_files' => $rfq->attachments
                ->map(fn ($attachment) => $this->rfqFormAttachmentPayload($attachment))
                ->values()
                ->all(),
            'supplier_recipient_ids' => $rfq->supplierRecipients
                ->pluck('supplier_service_listing_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'items' => $rfq->items->isNotEmpty()
                ? $rfq->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'part_no' => $item->part_no ?? '',
                    'quantity' => $item->quantity !== null ? rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') : '',
                    'unit' => $item->unit ?? '',
                    'manufacturer' => $item->manufacturer ?? '',
                    'model_type' => $item->model_type ?? '',
                    'serial_number' => $item->serial_number ?? '',
                    'catalog_code' => $item->catalog_code ?? '',
                    'rob' => $item->rob !== null ? rtrim(rtrim(number_format((float) $item->rob, 2, '.', ''), '0'), '.') : '',
                    'drawing_number' => $item->drawing_number ?? '',
                    'quality' => $item->quality ?? '',
                    'comments' => $item->comments ?? '',
                    'files' => $item->attachments
                        ->map(fn ($attachment) => $this->rfqFormAttachmentPayload($attachment))
                        ->values()
                        ->all(),
                ])->values()->all()
                : $this->emptyRfqDefaults()['items'],
        ];
    }

    private function rfqFormAttachmentPayload($attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'url' => Storage::disk($attachment->disk)->url($attachment->path),
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'existing' => true,
        ];
    }

    private function rfqFormPayload(array $defaults, string $actionUrl, array $importTemplate, string $mode, string $submitMethod, string $backUrl, array $editPolicy, array $formContext = []): array
    {
        return [
            'defaults' => $defaults,
            'unitOptions' => self::UNIT_OPTIONS,
            'qualityOptions' => self::QUALITY_OPTIONS,
            'countryOptions' => $formContext['country_options'] ?? $this->rfqCountryOptions(),
            'portsByCountry' => $formContext['ports_by_country'] ?? [],
            'supplierCategories' => $this->supplierCategoryOptions(),
            'actionUrl' => $actionUrl,
            'supplierMatchesUrl' => route('rfqs.supplier-matches'),
            'supplierSuggestionsUrl' => route('rfqs.supplier-suggestions'),
            'supplierBrandsUrl' => route('services.filters.brands'),
            'supplierSubcategoriesUrl' => route('services.filters.subcategories'),
            'importTemplate' => $importTemplate,
            'mode' => $mode,
            'submitMethod' => $submitMethod,
            'backUrl' => $backUrl,
            'editPolicy' => $editPolicy,
            'supplierTarget' => $formContext['supplier_target'] ?? null,
        ];
    }

    private function supplierTargetFromCreateRequest(Request $request): ?array
    {
        if ((string) $request->query('source', '') !== 'supplier_detail') {
            return null;
        }

        $sellerId = $request->integer('supplier');

        if (! $sellerId) {
            return null;
        }

        $categoryId = $request->integer('category_id') ?: null;
        $subcategoryId = $request->integer('subcategory_id') ?: null;

        $listingQuery = SupplierServiceListing::query()
            ->visible()
            ->where('seller_id', $sellerId)
            ->with('ports');

        $listings = (clone $listingQuery)
            ->when($subcategoryId, fn ($query) => $query->where('subcategory_id', $subcategoryId))
            ->when(! $subcategoryId && $categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('company_name')
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->get();

        if ($listings->isEmpty()) {
            $listings = $listingQuery
                ->orderBy('company_name')
                ->orderBy('category_name')
                ->orderBy('subcategory_name')
                ->get();
        }

        if ($listings->isEmpty()) {
            return null;
        }

        return $this->buildSupplierTargetContext(
            $listings,
            companyName: $listings->first()->company_name,
            requestType: 'service_request',
            requestTypeLocked: true,
            categoryIds: $categoryId ? [$categoryId] : [],
            subcategoryIds: $subcategoryId ? [$subcategoryId] : [],
            backUrl: $this->validatedInternalBackUrl((string) $request->query('return_to', ''), $request, route('buyer.requests'))
        );
    }

    private function supplierTargetFromRfq(Rfq $rfq): ?array
    {
        if ($rfq->request_type !== 'service_request' || ! $rfq->isPrivateSupplierRequest()) {
            return null;
        }

        $recipientSellerIds = $rfq->supplierRecipients
            ->pluck('seller_id')
            ->filter()
            ->unique()
            ->values();

        if ($recipientSellerIds->count() !== 1) {
            return null;
        }

        $listingIds = $rfq->supplierRecipients
            ->pluck('supplier_service_listing_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($listingIds->isEmpty()) {
            return null;
        }

        $listings = SupplierServiceListing::query()
            ->whereIn('id', $listingIds->all())
            ->with('ports')
            ->orderBy('company_name')
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->get();

        if ($listings->isEmpty()) {
            return null;
        }

        return $this->buildSupplierTargetContext(
            $listings,
            companyName: $rfq->supplierRecipients->pluck('company_name')->filter()->first() ?: $listings->first()->company_name,
            requestType: 'service_request',
            requestTypeLocked: true,
            categoryIds: collect($rfq->category_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all(),
            subcategoryIds: collect($rfq->subcategory_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all(),
            backUrl: null
        );
    }

    private function buildSupplierTargetContext($listings, string $companyName, string $requestType, bool $requestTypeLocked, array $categoryIds = [], array $subcategoryIds = [], ?string $backUrl = null): ?array
    {
        $listings = collect($listings)
            ->filter()
            ->values();

        if ($listings->isEmpty()) {
            return null;
        }

        $sellerIds = $listings
            ->pluck('seller_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($sellerIds->count() !== 1) {
            return null;
        }

        $seller = User::query()->find($sellerIds->first());

        if (! $seller) {
            return null;
        }

        $portsByCountry = $seller->servicePorts()
            ->active()
            ->orderBy('country_name')
            ->orderBy('port_name')
            ->get(['ports.id', 'ports.country_code', 'ports.country_name', 'ports.port_name', 'ports.unlocode'])
            ->groupBy(function (Port $port) {
                return CountryNameResolver::resolve((string) ($port->country_code ?: $port->country_name))
                    ?? $port->country_name
                    ?? $port->country_code;
            })
            ->map(function ($ports) {
                return $ports
                    ->sortBy([
                        ['port_name', 'asc'],
                        ['unlocode', 'asc'],
                    ])
                    ->unique(fn (Port $port) => sprintf('%s|%s', (int) $port->id, (string) $port->unlocode))
                    ->map(fn (Port $port) => [
                        'id' => (int) $port->id,
                        'name' => $port->port_name,
                        'unlocode' => $port->unlocode,
                    ])
                    ->values()
                    ->all();
            })
            ->filter(fn (array $ports) => $ports !== [])
            ->sortKeys();

        if ($portsByCountry->isEmpty()) {
            return null;
        }

        $countryOptions = $portsByCountry
            ->keys()
            ->filter()
            ->values()
            ->all();

        if ($countryOptions === []) {
            return null;
        }

        return [
            'company_name' => $companyName,
            'source' => 'supplier_detail',
            'supplier_id' => (int) $seller->id,
            'prefill_category_id' => count($categoryIds) === 1 ? (int) $categoryIds[0] : null,
            'prefill_subcategory_id' => count($subcategoryIds) === 1 ? (int) $subcategoryIds[0] : null,
            'candidate_listing_ids' => $listings
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'country_options' => $countryOptions,
            'ports_by_country' => $portsByCountry->toArray(),
            'message' => "This RFQ will be sent only to {$companyName}.",
            'scope_note' => "Available countries and ports are limited to {$companyName}'s service coverage.",
            'request_type' => $requestType,
            'request_type_locked' => $requestTypeLocked,
            'category_ids' => array_values(array_filter(array_map('intval', $categoryIds))),
            'subcategory_ids' => array_values(array_filter(array_map('intval', $subcategoryIds))),
            'back_url' => $backUrl,
        ];
    }

    private function validatedInternalBackUrl(string $url, Request $request, string $fallback): string
    {
        $normalized = trim($url);

        if ($normalized === '') {
            return $fallback;
        }

        if (Str::startsWith($normalized, '/')) {
            return $normalized;
        }

        $root = rtrim($request->root(), '/');

        if (Str::startsWith($normalized, $root.'/')) {
            return $normalized;
        }

        return $fallback;
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

    private function normalizedPortIdsByCountry(Rfq $rfq): array
    {
        return collect($this->normalizedPortsByCountry($rfq))
            ->mapWithKeys(fn (array $group) => [
                $group['country'] => collect($group['ports'])
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
            ])
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
        return Cache::remember('rfq_active_port_counts_by_country_v1', now()->addMinutes(30), function (): array {
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

    private function rfqCountryOptions(): array
    {
        return Port::query()
            ->active()
            ->orderBy('country_name')
            ->distinct()
            ->pluck('country_name')
            ->map(fn (string $country) => CountryNameResolver::resolve($country) ?? $country)
            ->filter()
            ->unique()
            ->sort(fn ($left, $right) => strcasecmp((string) $left, (string) $right))
            ->values()
            ->all();
    }

    private function syncRfqItems(Rfq $rfq, array $items, Request $request): void
    {
        $existingItems = $rfq->items()->with('attachments')->get()->keyBy('id');
        $keepIds = [];

        foreach ($items as $index => $itemData) {
            $itemPayload = [
                'line_no' => $index + 1,
                'product_name' => trim((string) $itemData['product_name']),
                'part_no' => filled($itemData['part_no'] ?? null) ? trim((string) $itemData['part_no']) : null,
                'quantity' => $itemData['quantity'],
                'unit' => strtoupper(trim((string) $itemData['unit'])),
                'manufacturer' => filled($itemData['manufacturer'] ?? null) ? trim((string) $itemData['manufacturer']) : null,
                'model_type' => filled($itemData['model_type'] ?? null) ? trim((string) $itemData['model_type']) : null,
                'serial_number' => filled($itemData['serial_number'] ?? null) ? trim((string) $itemData['serial_number']) : null,
                'catalog_code' => filled($itemData['catalog_code'] ?? null) ? trim((string) $itemData['catalog_code']) : null,
                'rob' => filled($itemData['rob'] ?? null) ? $itemData['rob'] : null,
                'drawing_number' => filled($itemData['drawing_number'] ?? null) ? trim((string) $itemData['drawing_number']) : null,
                'quality' => $itemData['quality'] ?? null,
                'comments' => filled($itemData['comments'] ?? null) ? trim((string) $itemData['comments']) : null,
            ];

            $existing = filled($itemData['id'] ?? null) ? $existingItems->get((int) $itemData['id']) : null;
            $item = $existing
                ? tap($existing)->update($itemPayload)
                : $rfq->items()->create($itemPayload);

            $keepIds[] = $item->id;

            if ($existing) {
                $retainedAttachmentIds = collect($itemData['existing_attachment_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                $existing->attachments()
                    ->whereNotIn('id', $retainedAttachmentIds->all() ?: [0])
                    ->get()
                    ->each(function ($attachment): void {
                        Storage::disk($attachment->disk)->delete($attachment->path);
                        $attachment->delete();
                    });
            }

            foreach (($request->file("items.{$index}.files") ?? []) as $file) {
                $path = $file->store("rfqs/{$rfq->id}/items/{$item->id}", 'public');

                $item->attachments()->create([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        $rfq->items()->with('attachments')->get()
            ->reject(fn ($item) => in_array($item->id, $keepIds, true))
            ->each(function ($item) {
                $this->deleteItemAttachmentFiles($item);
                $item->delete();
            });
    }

    private function deleteRfqItems(Rfq $rfq): void
    {
        $rfq->items()->with('attachments')->get()->each(function ($item) {
            $this->deleteItemAttachmentFiles($item);
            $item->delete();
        });
    }

    private function deleteRfqAttachments(Rfq $rfq): void
    {
        $rfq->attachments->each(function ($attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });
    }

    private function deleteItemAttachmentFiles($item): void
    {
        $item->attachments->each(function ($attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });

        OfferItem::query()
            ->where('rfq_item_id', $item->id)
            ->with('attachments')
            ->get()
            ->each(function (OfferItem $offerItem): void {
                $offerItem->attachments->each(function ($attachment): void {
                    Storage::disk($attachment->disk)->delete($attachment->path);
                    $attachment->delete();
                });
            });
    }

    private function deleteOfferTreeFiles(Rfq $rfq): void
    {
        $rfq->offers()
            ->with(['attachments', 'items.attachments', 'invoices', 'messages'])
            ->get()
            ->each(function (Offer $offer): void {
                $offer->attachments->each(function ($attachment): void {
                    Storage::disk($attachment->disk)->delete($attachment->path);
                    $attachment->delete();
                });

                $offer->items->each(function (OfferItem $item): void {
                    $item->attachments->each(function ($attachment): void {
                        Storage::disk($attachment->disk)->delete($attachment->path);
                        $attachment->delete();
                    });
                });

                $offer->invoices->each(function ($invoice): void {
                    if ($invoice->invoice_document_path && $invoice->invoice_document_disk) {
                        Storage::disk($invoice->invoice_document_disk)->delete($invoice->invoice_document_path);
                    }

                    if ($invoice->payment_proof_document_path && $invoice->payment_proof_document_disk) {
                        Storage::disk($invoice->payment_proof_document_disk)->delete($invoice->payment_proof_document_path);
                    }
                });

                $offer->messages->each(function ($message): void {
                    if ($message->attachment_path && $message->attachment_disk) {
                        Storage::disk($message->attachment_disk)->delete($message->attachment_path);
                    }
                });
            });
    }

    private function syncSupplierRecipients(Rfq $rfq, $selectedRecipients): array
    {
        $existingRecipients = $rfq->supplierRecipients()->get();
        $existingByListingId = $existingRecipients
            ->filter(fn ($recipient) => filled($recipient->supplier_service_listing_id))
            ->keyBy(fn ($recipient) => (int) $recipient->supplier_service_listing_id);
        $existingByDeliveryKey = $existingRecipients
            ->keyBy(fn ($recipient) => $this->supplierDeliveryKey($recipient->seller_id, $recipient->company_name));
        $keepIds = [];
        $newRecipientIds = [];

        foreach ($selectedRecipients as $recipient) {
            $key = $this->supplierDeliveryKey($recipient->seller_id, $recipient->company_name);
            $listingId = (int) $recipient->id;
            $primaryPort = $recipient->ports->first();

            $payload = [
                'supplier_service_listing_id' => $listingId,
                'seller_id' => $recipient->seller_id,
                'company_name' => $recipient->company_name,
                'category_name' => $recipient->category_name,
                'subcategory_name' => $recipient->subcategory_name,
                'country_name' => $recipient->country ?: $primaryPort?->country_name,
                'port_name' => $primaryPort?->port_name,
            ];

            $existingRecipient = $existingByListingId->get($listingId)
                ?? $existingByDeliveryKey->get($key);

            if ($existingRecipient) {
                $existingRecipient->update($payload);
                $keepIds[] = $existingRecipient->id;
                continue;
            }

            $created = $rfq->supplierRecipients()->create($payload);
            $keepIds[] = $created->id;
            $newRecipientIds[] = $created->id;
        }

        $rfq->supplierRecipients()->get()
            ->reject(fn ($recipient) => in_array($recipient->id, $keepIds, true))
            ->each
            ->delete();

        return $newRecipientIds;
    }

    private function templateAliasesForUser($user): array
    {
        $template = $user?->rfqImportTemplate;

        if (! $template || ! $template->is_active) {
            return [
                'general' => [],
                'items' => [],
            ];
        }

        return [
            'general' => is_array($template->general_aliases) ? $template->general_aliases : [],
            'items' => is_array($template->item_aliases) ? $template->item_aliases : [],
        ];
    }

    private function sanitizeTemplateFieldAliases(array $input, array $allowedFields): array
    {
        $sanitized = [];

        foreach ($allowedFields as $field) {
            $rawValue = $input[$field] ?? [];
            $aliases = collect(is_array($rawValue) ? $rawValue : preg_split('/[\r\n,]+/', (string) $rawValue))
                ->map(fn ($alias) => trim((string) $alias))
                ->filter()
                ->unique()
                ->take(30)
                ->values()
                ->all();

            if ($aliases !== []) {
                $sanitized[$field] = $aliases;
            }
        }

        return $sanitized;
    }

    private function formatImportTemplate(?RfqImportTemplate $template): array
    {
        $general = collect(self::IMPORT_TEMPLATE_GENERAL_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => implode(', ', (array) data_get($template?->general_aliases, $field, []))])
            ->all();

        $items = collect(self::IMPORT_TEMPLATE_ITEM_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => implode(', ', (array) data_get($template?->item_aliases, $field, []))])
            ->all();

        return [
            'name' => $template?->name ?: 'My RFQ Import Template',
            'general' => $general,
            'items' => $items,
            'hasSavedTemplate' => $template !== null,
        ];
    }

    private function supplierDeliveryKey(?int $sellerId, ?string $companyName): string
    {
        if ($sellerId) {
            return 'seller:'.$sellerId;
        }

        return 'company:'.Str::lower(trim((string) $companyName));
    }

    private function supplierCategoryOptions(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->values()
            ->all();
    }

    private function editPolicyForRfq(Rfq $rfq): array
    {
        if ($rfq->canBeFullyEdited()) {
            return [
                'can_edit' => true,
                'general_only' => false,
                'can_delete' => $rfq->canBeDeleted(),
                'reason' => $rfq->editReason(),
            ];
        }

        if ($rfq->canBeGeneralInfoEditedOnly()) {
            return [
                'can_edit' => true,
                'general_only' => true,
                'can_delete' => false,
                'reason' => $rfq->editReason(),
            ];
        }

        return [
            'can_edit' => false,
            'general_only' => false,
            'can_delete' => false,
            'reason' => $rfq->editReason(),
        ];
    }

    private function validateSellerOfferPayload(Request $request, Rfq $rfq, string $intent): array
    {
        $rfqItems = $rfq->items->keyBy('id');
        $isSpareParts = $rfq->request_type === 'spare_parts';

        $validator = validator($request->all(), [
            'intent' => ['required', Rule::in([Offer::STATUS_DRAFT, Offer::STATUS_SUBMITTED, 'draft', 'submit'])],
            'items' => [$isSpareParts ? 'required' : 'nullable', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.offer_qty' => ['nullable', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'gt:0'],
            'items.*.lead_time' => ['nullable', 'integer', 'gte:1'],
            'items.*.quality' => ['nullable', Rule::in(self::QUALITY_OPTIONS)],
            'items.*.brand_note' => ['nullable', 'string', 'max:255'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
            'items.*.existing_attachment_ids' => ['nullable', 'array'],
            'items.*.existing_attachment_ids.*' => ['integer'],
            'items.*.files' => ['nullable', 'array'],
            'items.*.files.*' => ['file', 'mimes:pdf,png,jpg,jpeg,webp,xlsx,xls,csv', 'max:15360'],
            'service_total_price' => ['nullable', 'numeric', 'gt:0'],
            'completion_time' => ['nullable', 'string', 'max:255'],
            'offer_validity' => ['nullable', 'string', 'max:255'],
            'existing_offer_attachment_ids' => ['nullable', 'array'],
            'existing_offer_attachment_ids.*' => ['integer'],
            'service_files' => ['nullable', 'array'],
            'service_files.*' => ['file', 'mimes:pdf,png,jpg,jpeg,webp,xlsx,xls,csv,doc,docx', 'max:15360'],
            'including_tax' => ['required', 'boolean'],
            'tax_amount' => ['nullable', 'numeric', 'gte:0'],
            'including_mobilization' => ['nullable', 'boolean'],
            'mobilization_cost' => ['nullable', 'numeric', 'gte:0'],
            'including_packing' => ['nullable', 'boolean'],
            'packing_cost' => ['nullable', 'numeric', 'gte:0'],
            'including_freight' => ['nullable', 'boolean'],
            'freight_cost' => ['nullable', 'numeric', 'gte:0'],
            'delivery_terms' => ['nullable', 'string', 'max:40'],
            'other_delivery_terms' => ['nullable', 'string', 'max:255'],
            'award_scope_policy' => ['nullable', Rule::in([
                Offer::AWARD_SCOPE_PARTIAL_ALLOWED,
                Offer::AWARD_SCOPE_FULL_SCOPE_REQUIRED,
            ])],
            'payment_order_confirmation' => ['nullable', 'numeric', 'gte:0'],
            'payment_before_shipment' => ['nullable', 'numeric', 'gte:0'],
            'payment_invoice_days' => ['nullable', 'integer', 'gte:0'],
            'other_payment_terms' => ['nullable', 'string', 'max:255'],
            'service_clarification' => ['nullable', 'string', 'max:5000'],
            'general_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $validator->after(function ($validator) use ($request, $rfqItems, $intent, $isSpareParts): void {
            if ($intent === Offer::STATUS_SUBMITTED) {
                $this->validateOfferPaymentTerms($validator, $request);
            }

            if (! $isSpareParts) {
                $totalPrice = trim((string) $request->input('service_total_price', ''));
                $completionTime = trim((string) $request->input('completion_time', ''));
                $offerValidity = trim((string) $request->input('offer_validity', ''));
                $hasStartedService = $totalPrice !== ''
                    || $completionTime !== ''
                    || $offerValidity !== ''
                    || trim((string) $request->input('service_clarification', '')) !== ''
                    || trim((string) $request->input('general_note', '')) !== ''
                    || collect($request->input('existing_offer_attachment_ids', []))->filter()->isNotEmpty()
                    || collect($request->file('service_files') ?? [])->isNotEmpty();

                if ($intent === Offer::STATUS_SUBMITTED && ($hasStartedService || $totalPrice === '')) {
                    if ($totalPrice === '') {
                        $validator->errors()->add('service_total_price', 'Total price is required before submission.');
                    } elseif ((float) $totalPrice <= 0) {
                        $validator->errors()->add('service_total_price', 'Total price must be greater than 0.');
                    }

                    if ($completionTime === '') {
                        $validator->errors()->add('completion_time', 'Completion time is required before submission.');
                    }

                    if ($offerValidity === '') {
                        $validator->errors()->add('offer_validity', 'Offer validity is required before submission.');
                    }
                }

                foreach ([
                    ['including_tax', 'tax_amount'],
                    ['including_mobilization', 'mobilization_cost'],
                ] as [$flagField, $amountField]) {
                    $included = filter_var($request->input($flagField, false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $rawValue = trim((string) $request->input($amountField, ''));
                    $numericValue = $rawValue === '' ? null : (float) $rawValue;

                    if ($intent === Offer::STATUS_SUBMITTED && $included === false) {
                        if ($rawValue === '') {
                            $validator->errors()->add($amountField, 'This amount is required when not included.');
                        } elseif ($numericValue <= 0) {
                            $validator->errors()->add($amountField, 'This amount must be greater than 0.');
                        }
                    }
                }

                if (
                    $intent === Offer::STATUS_SUBMITTED
                    && $isSpareParts
                    && ! filled($request->input('award_scope_policy'))
                ) {
                    $validator->errors()->add('award_scope_policy', 'Award scope must be selected before submission.');
                }

                return;
            }

            $items = collect($request->input('items', []));
            $hasQuotedItem = false;
            $hasStartedItem = false;

            foreach ($items as $index => $item) {
                $rfqItemId = (int) ($item['id'] ?? 0);
                $rfqItem = $rfqItems->get($rfqItemId);

                if (! $rfqItem) {
                    $validator->errors()->add("items.{$index}.id", 'The selected RFQ item is invalid.');
                    continue;
                }

                $offerQty = trim((string) ($item['offer_qty'] ?? ''));
                $unitPrice = trim((string) ($item['unit_price'] ?? ''));
                $hasExtraDetail = collect([
                    $item['lead_time'] ?? null,
                    $item['quality'] ?? null,
                    $item['brand_note'] ?? null,
                    $item['remarks'] ?? null,
                ])->contains(fn ($value) => trim((string) $value) !== '')
                    || collect($item['existing_attachment_ids'] ?? [])->filter()->isNotEmpty()
                    || collect($request->file("items.{$index}.files") ?? [])->isNotEmpty();

                if ($offerQty !== '' && (float) $offerQty > (float) $rfqItem->quantity) {
                    $validator->errors()->add("items.{$index}.offer_qty", 'Offer quantity cannot exceed requested quantity.');
                }

                if ($intent !== Offer::STATUS_SUBMITTED) {
                    continue;
                }

                if ($offerQty !== '' || $unitPrice !== '' || $hasExtraDetail) {
                    $hasStartedItem = true;
                }

                if ($offerQty === '' && $unitPrice === '' && ! $hasExtraDetail) {
                    continue;
                }

                if ($offerQty === '' && ($unitPrice !== '' || $hasExtraDetail)) {
                    $validator->errors()->add("items.{$index}.offer_qty", 'Offer quantity is required.');
                    continue;
                }

                if (($offerQty !== '' || $hasExtraDetail) && $unitPrice === '') {
                    $validator->errors()->add("items.{$index}.unit_price", 'Unit price is required when offer quantity is entered.');
                    continue;
                }

                $leadTime = trim((string) ($item['lead_time'] ?? ''));

                if ($leadTime === '') {
                    $validator->errors()->add("items.{$index}.lead_time", 'Lead time is required when quoting an item.');
                    continue;
                }

                $hasQuotedItem = true;
            }

            if ($intent === Offer::STATUS_SUBMITTED && ! $hasQuotedItem && ! $hasStartedItem) {
                $validator->errors()->add('items', 'At least one RFQ item must be quoted before submission.');
            }

            if ($intent === Offer::STATUS_SUBMITTED && ! filled($request->input('award_scope_policy'))) {
                $validator->errors()->add('award_scope_policy', 'Award scope must be selected before submission.');
            }

            foreach ([
                ['including_tax', 'tax_amount'],
                ['including_packing', 'packing_cost'],
                ['including_freight', 'freight_cost'],
            ] as [$flagField, $amountField]) {
                $included = filter_var($request->input($flagField, false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $rawValue = trim((string) $request->input($amountField, ''));
                $numericValue = $rawValue === '' ? null : (float) $rawValue;

                if ($intent === Offer::STATUS_SUBMITTED && $included === false) {
                    if ($rawValue === '') {
                        $validator->errors()->add($amountField, 'This amount is required when not included.');
                    } elseif ($numericValue <= 0) {
                        $validator->errors()->add($amountField, 'This amount must be greater than 0.');
                    }
                }
            }
        });

        return $validator->validate();
    }

    private function validateOfferPaymentTerms($validator, Request $request): void
    {
        $orderConfirmation = $this->paymentTermPercentValue($request->input('payment_order_confirmation'));
        $beforeShipment = $this->paymentTermPercentValue($request->input('payment_before_shipment'));
        $invoiceDays = $this->paymentInvoiceDaysValue($request->input('payment_invoice_days'));
        $otherPaymentTerms = trim((string) $request->input('other_payment_terms', ''));

        if ($orderConfirmation > 100) {
            $validator->errors()->add('payment_order_confirmation', 'Order confirmation percentage cannot exceed 100.');
        }

        if ($beforeShipment > 100) {
            $validator->errors()->add('payment_before_shipment', 'Before shipment percentage cannot exceed 100.');
        }

        $hasOrderConfirmation = $orderConfirmation > 0;
        $hasBeforeShipment = $beforeShipment > 0;
        $hasInvoiceDays = $invoiceDays > 0;
        $hasOtherPaymentTerms = $otherPaymentTerms !== '';

        if (! $hasOrderConfirmation && ! $hasBeforeShipment && ! $hasInvoiceDays && ! $hasOtherPaymentTerms) {
            $validator->errors()->add('payment_terms', 'At least one payment term is required before submission.');
            return;
        }

        $percentTotal = $orderConfirmation + $beforeShipment;

        if ($percentTotal > 100) {
            $validator->errors()->add('payment_terms', 'Payment percentages cannot exceed 100 in total.');
            return;
        }

        if ($percentTotal > 0 && $percentTotal < 100 && ! $hasInvoiceDays && ! $hasOtherPaymentTerms) {
            $validator->errors()->add('payment_terms', 'Explain the remaining balance with days from Invoice Date or Other Payment Terms.');
        }
    }

    private function paymentTermPercentValue(mixed $value): float
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return 0;
        }

        $normalized = str_replace(',', '.', $raw);

        return is_numeric($normalized) ? round((float) $normalized, 2) : 0;
    }

    private function paymentInvoiceDaysValue(mixed $value): int
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return 0;
        }

        return max((int) $raw, 0);
    }

    private function paymentTermFieldString(mixed $value): string
    {
        $normalized = $this->paymentTermPercentValue($value);

        return $normalized > 0 ? $this->decimalString($normalized) : '';
    }

    private function paymentInvoiceDaysFieldString(mixed $value): string
    {
        $normalized = $this->paymentInvoiceDaysValue($value);

        return $normalized > 0 ? (string) $normalized : '';
    }

    /**
     * @return array{payment_order_confirmation: float|null, payment_before_shipment: float|null, payment_invoice_days: int|null, other_payment_terms: string|null}
     */
    private function normalizedPaymentTermsPayload(array $validated): array
    {
        $orderConfirmation = $this->paymentTermPercentValue($validated['payment_order_confirmation'] ?? null);
        $beforeShipment = $this->paymentTermPercentValue($validated['payment_before_shipment'] ?? null);
        $invoiceDays = $this->paymentInvoiceDaysValue($validated['payment_invoice_days'] ?? null);
        $otherPaymentTerms = trim((string) ($validated['other_payment_terms'] ?? ''));

        return [
            'payment_order_confirmation' => $orderConfirmation > 0 ? $orderConfirmation : null,
            'payment_before_shipment' => $beforeShipment > 0 ? $beforeShipment : null,
            'payment_invoice_days' => $invoiceDays > 0 ? $invoiceDays : null,
            'other_payment_terms' => $otherPaymentTerms !== '' ? $otherPaymentTerms : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function extractOfferItemsPayload(array $items, Rfq $rfq): array
    {
        $rfqItems = $rfq->items->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($rfqItems): ?array {
                $rfqItemId = (int) ($item['id'] ?? 0);
                $rfqItem = $rfqItems->get($rfqItemId);

                if (! $rfqItem) {
                    return null;
                }

                $offerQty = trim((string) ($item['offer_qty'] ?? ''));
                $unitPrice = trim((string) ($item['unit_price'] ?? ''));
                $leadTime = trim((string) ($item['lead_time'] ?? ''));
                $quality = trim((string) ($item['quality'] ?? ''));
                $manufacturer = trim((string) ($item['brand_note'] ?? ''));
                $remarks = trim((string) ($item['remarks'] ?? ''));

                if ($offerQty === '' && $unitPrice === '' && $leadTime === '' && $quality === '' && $manufacturer === '' && $remarks === '') {
                    return null;
                }

                if ($offerQty === '' || $unitPrice === '') {
                    return null;
                }

                $offerQtyValue = round((float) $offerQty, 2);
                $unitPriceValue = round((float) $unitPrice, 2);

                return [
                    'rfq_item_id' => $rfqItem->id,
                    'line_no' => (int) ($rfqItem->line_no ?? 0),
                    'offer_qty' => $offerQtyValue,
                    'unit_price' => $unitPriceValue,
                    'line_total' => round($offerQtyValue * $unitPriceValue, 2),
                    'delivery_time' => $leadTime !== '' ? (string) max((int) $leadTime, 1) : null,
                    'quality' => $quality !== '' ? $quality : null,
                    'manufacturer' => $manufacturer !== '' ? $manufacturer : null,
                    'remarks' => $remarks !== '' ? $remarks : null,
                    'existing_attachment_ids' => collect($item['existing_attachment_ids'] ?? [])
                        ->map(fn ($id) => (int) $id)
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $quotedItems
     * @param  array<string, mixed>  $validated
     * @return array<string, float>
     */
    private function calculateOfferTotals(Rfq $rfq, array $quotedItems, array $validated): array
    {
        if ($rfq->request_type === 'service_request') {
            $totalOfferAmount = round((float) ($validated['service_total_price'] ?? 0), 2);
            $taxAmount = ! empty($validated['including_tax']) ? 0 : round((float) ($validated['tax_amount'] ?? 0), 2);
            $mobilizationCost = ! empty($validated['including_mobilization']) ? 0 : round((float) ($validated['mobilization_cost'] ?? 0), 2);

            return [
                'total_offer_amount' => $totalOfferAmount,
                'grand_total' => round($totalOfferAmount + $taxAmount + $mobilizationCost, 2),
            ];
        }

        $totalOfferAmount = round(collect($quotedItems)->sum('line_total'), 2);
        $taxAmount = ! empty($validated['including_tax']) ? 0 : round((float) ($validated['tax_amount'] ?? 0), 2);
        $packingCost = ! empty($validated['including_packing']) ? 0 : round((float) ($validated['packing_cost'] ?? 0), 2);
        $freightCost = ! empty($validated['including_freight']) ? 0 : round((float) ($validated['freight_cost'] ?? 0), 2);

        return [
            'total_offer_amount' => $totalOfferAmount,
            'grand_total' => round($totalOfferAmount + $taxAmount + $packingCost + $freightCost, 2),
        ];
    }

    private function decimalString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function buyerRfqPayload(Rfq $rfq, ?int $selectedOrderOfferId = null): array
    {
        $status = $rfq->hasCompletedConfirmedOrders()
            ? 'completed'
            : ($rfq->hasConfirmedAwards()
                ? 'award_confirmed'
                : ($rfq->effectiveStatus() === 'submitted' ? 'open' : $rfq->effectiveStatus()));
        $countryNames = collect($rfq->country_names ?? [])->filter()->values()->all();
        $compareUrl = $rfq->offersCount() > 0 && $status !== 'completed'
            ? route('buyer.rfqs.compare', $rfq)
            : null;

        return [
            'id' => $rfq->id,
            'request_type' => $rfq->request_type,
            'reference_no' => $rfq->reference_no,
            'company_name' => $rfq->company_name,
            'ship_name' => $rfq->ship_name,
            'imo_number' => $rfq->imo_number,
            'country_names' => $countryNames,
            'ports_by_country' => $this->normalizedPortsByCountry($rfq),
            'port_totals_by_country' => $this->activePortCountsForCountries($countryNames),
            'selected_categories' => $this->selectedCategoryNames($rfq),
            'selected_subcategories' => $this->selectedSubcategoryNames($rfq),
            'selected_brands' => $this->selectedBrandNames($rfq),
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'currency' => $rfq->currency,
            'priority' => $rfq->priority,
            'status' => $status,
            'general_notes' => $rfq->general_notes,
            'service_title' => $rfq->service_title,
            'service_description' => $rfq->service_description,
            'items_count' => $rfq->items_count,
            'offers_count' => $rfq->offersCount(),
            'compare_url' => $compareUrl,
            'selected_order_offer_id' => $selectedOrderOfferId,
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
                'quantity' => $item->quantity !== null ? rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') : null,
                'unit' => $item->unit,
                'rob' => $item->rob !== null ? rtrim(rtrim(number_format((float) $item->rob, 2, '.', ''), '0'), '.') : null,
                'quality' => $item->quality,
                'comments' => $item->comments,
                'attachments' => $item->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'url' => Storage::disk($attachment->disk)->url($attachment->path),
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                ])->values()->all(),
            ])->values()->all(),
            'attachments' => $rfq->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'url' => Storage::disk($attachment->disk)->url($attachment->path),
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ])->values()->all(),
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

    private function buyerComparePayload(Rfq $rfq): array
    {
        $status = $rfq->hasCompletedConfirmedOrders()
            ? 'completed'
            : ($rfq->hasConfirmedAwards()
                ? 'award_confirmed'
                : ($rfq->effectiveStatus() === 'submitted' ? 'open' : $rfq->effectiveStatus()));
        $countryNames = collect($rfq->country_names ?? [])->filter()->values()->all();

        return [
            'id' => $rfq->id,
            'request_type' => $rfq->request_type,
            'reference_no' => $rfq->reference_no,
            'company_name' => $rfq->company_name,
            'ship_name' => $rfq->ship_name,
            'imo_number' => $rfq->imo_number,
            'country_names' => $countryNames,
            'ports_by_country' => $this->normalizedPortsByCountry($rfq),
            'port_totals_by_country' => $this->activePortCountsForCountries($countryNames),
            'selected_categories' => $this->selectedCategoryNames($rfq),
            'selected_subcategories' => $this->selectedSubcategoryNames($rfq),
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'currency' => $rfq->currency,
            'priority' => $rfq->priority,
            'status' => $status,
            'general_notes' => $rfq->general_notes,
            'service_title' => $rfq->service_title,
            'service_description' => $rfq->service_description,
            'items' => $rfq->request_type === 'spare_parts'
                ? $rfq->items->map(fn ($item) => [
                    'id' => $item->id,
                    'line_no' => $item->line_no,
                    'product_name' => $item->product_name,
                    'part_no' => $item->part_no,
                    'manufacturer' => $item->manufacturer,
                    'model_type' => $item->model_type,
                    'catalog_code' => $item->catalog_code,
                    'serial_number' => $item->serial_number,
                    'drawing_number' => $item->drawing_number,
                    'quantity' => $item->quantity !== null ? rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') : null,
                    'unit' => $item->unit,
                    'rob' => $item->rob !== null ? rtrim(rtrim(number_format((float) $item->rob, 2, '.', ''), '0'), '.') : null,
                    'quality' => $item->quality,
                    'comments' => $item->comments,
                    'attachments' => $item->attachments->map(fn ($attachment) => [
                        'name' => $attachment->original_name,
                        'url' => Storage::disk($attachment->disk)->url($attachment->path),
                    ])->values()->all(),
                ])->values()->all()
                : [],
            'attachments' => $rfq->request_type === 'service_request'
                ? $rfq->attachments->map(fn ($attachment) => [
                    'name' => $attachment->original_name,
                    'url' => Storage::disk($attachment->disk)->url($attachment->path),
                ])->values()->all()
                : [],
        ];
    }

    private function determineVisibilityScope(string $requestType, Collection $selectedRecipients, bool $isSupplierDetailSource): string
    {
        if ($isSupplierDetailSource && $requestType === 'service_request') {
            return Rfq::VISIBILITY_PRIVATE_SUPPLIER;
        }

        return Rfq::VISIBILITY_PUBLIC_MARKETPLACE;
    }

    private function sellerOfferFallbackUrl(Rfq $rfq, User $user): string
    {
        if ($this->canSellerAccessWorkspace($rfq, $user)) {
            return route('seller.rfqs.show', $rfq);
        }

        return route('seller.requests');
    }

    private function ensureSellerRecipientSnapshotForOffer(Rfq $rfq, User $seller): void
    {
        if (! $rfq->isPublicMarketplace()) {
            return;
        }

        $listing = $this->rfqAccess->firstMatchingPublicListingForSeller($rfq, $seller);

        if (! $listing) {
            return;
        }

        $matchedPort = $this->matchingListingPortForRfq($listing, $rfq) ?? $listing->ports->first();
        $existingRecipient = $rfq->supplierRecipients()
            ->where(function ($query) use ($seller, $listing) {
                $query->where('seller_id', $seller->id);

                if ($listing->id) {
                    $query->orWhere('supplier_service_listing_id', $listing->id);
                }
            })
            ->latest('id')
            ->first();

        $payload = [
            'supplier_service_listing_id' => $listing->id,
            'seller_id' => $seller->id,
            'company_name' => $listing->company_name ?: $seller->company_name ?: $seller->name,
            'category_name' => $listing->category_name,
            'subcategory_name' => $listing->subcategory_name,
            'country_name' => $matchedPort
                ? (CountryNameResolver::resolve((string) ($matchedPort->country_name ?: $matchedPort->country_code))
                    ?? $matchedPort->country_name
                    ?? $matchedPort->country_code)
                : ($listing->country ?: null),
            'port_name' => $matchedPort?->port_name,
        ];

        if ($existingRecipient) {
            $existingRecipient->update($payload);

            return;
        }

        $rfq->supplierRecipients()->create($payload);
    }

    private function matchingListingPortForRfq(SupplierServiceListing $listing, Rfq $rfq): ?object
    {
        $requestedPorts = collect($this->normalizedPortsByCountry($rfq))
            ->flatMap(function (array $group) {
                $country = trim((string) ($group['country'] ?? ''));
                $countryKey = $country === '' ? null : mb_strtolower($country);

                return collect($group['ports'] ?? [])
                    ->map(fn (array $port) => [
                        'country' => $countryKey,
                        'name' => $this->locationKey($port['name'] ?? null),
                        'unlocode' => strtoupper(trim((string) ($port['unlocode'] ?? ''))),
                    ]);
            })
            ->filter(fn (array $port) => filled($port['name']) || filled($port['unlocode']))
            ->values();

        if ($requestedPorts->isEmpty()) {
            return $listing->ports->first();
        }

        return $listing->ports->first(function ($port) use ($requestedPorts) {
            $portCountry = CountryNameResolver::resolve((string) ($port->country_name ?: $port->country_code))
                ?? $port->country_name
                ?? $port->country_code;
            $portCountryKey = $this->locationKey($portCountry);
            $portNameKey = $this->locationKey($port->port_name);
            $portUnlocode = strtoupper(trim((string) ($port->unlocode ?? '')));

            return $requestedPorts->contains(function (array $requestedPort) use ($portCountryKey, $portNameKey, $portUnlocode) {
                if (filled($requestedPort['country']) && filled($portCountryKey) && $requestedPort['country'] !== $portCountryKey) {
                    return false;
                }

                if (filled($requestedPort['unlocode']) && $portUnlocode !== '' && $requestedPort['unlocode'] === $portUnlocode) {
                    return true;
                }

                return filled($requestedPort['name']) && filled($portNameKey) && $requestedPort['name'] === $portNameKey;
            });
        }) ?? $listing->ports->first();
    }

    private function locationKey($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_strtolower($text);
    }

    private function serializeBuyerCompareOffers(
        \Illuminate\Support\Collection $offers,
        \Illuminate\Support\Collection $awardRows,
        string $requestType
    ): array
    {
        $awardByOfferId = $awardRows
            ->filter(fn (OfferAward $award) => $award->offer_item_id === null)
            ->keyBy('offer_id');

        $awardByOfferItemId = $awardRows
            ->filter(fn (OfferAward $award) => $award->offer_item_id !== null)
            ->keyBy('offer_item_id');

        return $offers->map(function (Offer $offer) use ($awardByOfferId, $awardByOfferItemId, $requestType): array {
            $offerAward = $awardByOfferId->get($offer->id);

            return [
                'id' => $offer->id,
                'currency' => $offer->currency,
                'total_offer_amount' => $this->decimalString($offer->total_offer_amount),
                'including_tax' => (bool) $offer->including_tax,
                'tax_amount' => $this->decimalString($offer->tax_amount),
                'including_packing' => $requestType === 'spare_parts' ? (bool) $offer->including_packing : false,
                'packing_cost' => $requestType === 'spare_parts' ? $this->decimalString($offer->packing_cost) : '',
                'including_freight' => $requestType === 'spare_parts' ? (bool) $offer->including_freight : false,
                'freight_cost' => $requestType === 'spare_parts' ? $this->decimalString($offer->freight_cost) : '',
                'including_mobilization' => $requestType === 'service_request' ? (bool) $offer->including_mobilization : false,
                'mobilization_cost' => $requestType === 'service_request' ? $this->decimalString($offer->mobilization_cost) : '',
                'grand_total' => $this->decimalString($offer->grand_total),
                'completion_time' => $requestType === 'service_request' ? ($offer->completion_time ?? '') : '',
                'offer_validity' => $requestType === 'service_request' ? ($offer->offer_validity ?? '') : '',
                'delivery_terms' => $offer->delivery_terms ?? '',
                'award_scope_policy' => $offer->awardScopePolicy(),
                'payment_order_confirmation' => $this->decimalString($offer->payment_order_confirmation),
                'payment_before_shipment' => $this->decimalString($offer->payment_before_shipment),
                'payment_invoice_days' => $offer->payment_invoice_days,
                'other_payment_terms' => $offer->other_payment_terms ?? '',
                'service_clarification' => $requestType === 'service_request' ? ($offer->service_clarification ?? '') : '',
                'general_note' => $offer->general_note ?? '',
                'seller' => [
                    'id' => $offer->seller?->id,
                    'name' => $offer->seller?->name ?? '-',
                    'company_name' => $offer->seller?->company_name ?? $offer->seller?->name ?? '-',
                    'logo_url' => $offer->seller?->company_logo_path
                        ? '/storage/'.ltrim($offer->seller->company_logo_path, '/')
                        : null,
                ],
                'current_award' => $offerAward ? [
                    'awarded_quantity' => $this->decimalString($offerAward->awarded_quantity),
                    'buyer_note' => $offerAward->buyer_note ?? '',
                ] : null,
                'attachments' => $requestType === 'service_request'
                    ? $offer->attachments->map(fn ($attachment) => [
                        'name' => $attachment->original_name,
                        'url' => Storage::disk($attachment->disk)->url($attachment->path),
                    ])->values()->all()
                    : [],
                'items' => $requestType === 'spare_parts'
                    ? $offer->items->map(function (OfferItem $item) use ($awardByOfferItemId): array {
                    $itemAward = $awardByOfferItemId->get($item->id);

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
                        'current_award' => $itemAward ? [
                            'awarded_quantity' => $this->decimalString($itemAward->awarded_quantity),
                            'buyer_note' => $itemAward->buyer_note ?? '',
                        ] : null,
                        'attachments' => $item->attachments->map(fn ($attachment) => [
                            'name' => $attachment->original_name,
                            'url' => Storage::disk($attachment->disk)->url($attachment->path),
                        ])->values()->all(),
                    ];
                    })->values()->all()
                    : [],
            ];
        })->values()->all();
    }

    private function serializeBuyerShowOffers(\Illuminate\Support\Collection $offers): array
    {
        return $offers->map(function (Offer $offer): array {
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
                'attachments' => $offer->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'url' => Storage::disk($attachment->disk)->url($attachment->path),
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                ])->values()->all(),
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
                        'attachments' => $item->attachments->map(fn ($attachment) => [
                            'id' => $attachment->id,
                            'name' => $attachment->original_name,
                            'url' => Storage::disk($attachment->disk)->url($attachment->path),
                            'mime_type' => $attachment->mime_type,
                            'size' => $attachment->size,
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    private function buyerAwardSummary(Rfq $rfq, \Illuminate\Support\Collection $awardRows): array
    {
        if ($rfq->request_type !== 'spare_parts') {
            $confirmedOffers = $awardRows->where('status', OfferAward::STATUS_CONFIRMED)->pluck('offer_id')->unique()->count();
            $draftOffers = $awardRows->where('status', OfferAward::STATUS_DRAFT)->pluck('offer_id')->unique()->count();

            return [
                'confirmed_offers' => $confirmedOffers,
                'draft_offers' => $draftOffers,
                'has_confirmed' => $confirmedOffers > 0,
                'has_draft' => $draftOffers > 0,
            ];
        }

        $requestedByItem = $rfq->items
            ->mapWithKeys(fn ($item) => [$item->id => (float) ($item->quantity ?? 0)]);

        $confirmedTotals = $awardRows
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->groupBy('rfq_item_id')
            ->map(fn ($rows) => round((float) $rows->sum('awarded_quantity'), 2));

        $draftTotals = $awardRows
            ->where('status', OfferAward::STATUS_DRAFT)
            ->groupBy('rfq_item_id')
            ->map(fn ($rows) => round((float) $rows->sum('awarded_quantity'), 2));

        $fullyAwarded = 0;
        $partiallyAwarded = 0;
        $unawarded = 0;

        foreach ($requestedByItem as $itemId => $requestedQty) {
            $confirmedQty = (float) ($confirmedTotals[$itemId] ?? 0);

            if ($confirmedQty >= $requestedQty && $requestedQty > 0) {
                $fullyAwarded++;
            } elseif ($confirmedQty > 0) {
                $partiallyAwarded++;
            } else {
                $unawarded++;
            }
        }

        return [
            'fully_awarded_items' => $fullyAwarded,
            'partially_awarded_items' => $partiallyAwarded,
            'unawarded_items' => $unawarded,
            'draft_awarded_items' => $draftTotals->filter(fn ($qty) => (float) $qty > 0)->count(),
            'confirmed_offer_count' => $awardRows->where('status', OfferAward::STATUS_CONFIRMED)->pluck('offer_id')->unique()->count(),
            'draft_offer_count' => $awardRows->where('status', OfferAward::STATUS_DRAFT)->pluck('offer_id')->unique()->count(),
        ];
    }

    private function confirmedAwardsPayload(Rfq $rfq, ?int $selectedOfferId = null): array
    {
        $confirmedAwards = OfferAward::query()
            ->where('rfq_id', $rfq->id)
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->when($selectedOfferId !== null, fn ($query) => $query->where('offer_id', $selectedOfferId))
            ->with([
                'offer:id,rfq_id,seller_id,currency,grand_total,delivery_terms,other_delivery_terms,payment_order_confirmation,payment_before_shipment,payment_invoice_days,other_payment_terms',
                'offer.seller:id,name,company_name',
                'offerItem:id,offer_id,rfq_item_id,unit_price',
                'offerItem.rfqItem:id,line_no,product_name,part_no,unit',
                'rfqItem:id,line_no,product_name,part_no,unit',
            ])
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get();

        if ($rfq->request_type === 'service_request') {
            $rows = $confirmedAwards
                ->whereNull('offer_item_id')
                ->groupBy('offer_id')
                ->map(function ($rows) use ($rfq) {
                    /** @var OfferAward|null $award */
                    $award = $rows->first();
                    $offer = $award?->offer;
                    $seller = $offer?->seller;

                    if (! $award || ! $offer || ! $seller) {
                        return null;
                    }

                    return [
                        'offer_id' => $offer->id,
                        'supplier_name' => $seller->company_name ?: $seller->name ?: '-',
                        'selected_total' => $this->decimalString($offer->grand_total),
                        'currency' => $offer->currency ?: $rfq->currency,
                        'delivery_terms' => $offer->delivery_terms ?? '',
                        'payment_order_confirmation' => $this->decimalString($offer->payment_order_confirmation),
                        'payment_before_shipment' => $this->decimalString($offer->payment_before_shipment),
                        'payment_invoice_days' => $offer->payment_invoice_days,
                        'other_payment_terms' => $offer->other_payment_terms ?? '',
                        'buyer_note' => $award->buyer_note ?? '',
                    ];
                })
                ->filter()
                ->values();

            return [
                'has_confirmed' => $rows->isNotEmpty(),
                'type' => 'service_request',
                'overall_selected_total' => $this->decimalString($rows->sum(fn (array $row) => (float) ($row['selected_total'] ?? 0))),
                'rows' => $rows->all(),
            ];
        }

        $rows = $confirmedAwards
            ->whereNotNull('offer_item_id')
            ->map(function (OfferAward $award) use ($rfq) {
                $offer = $award->offer;
                $seller = $offer?->seller;
                $offerItem = $award->offerItem;
                $rfqItem = $offerItem?->rfqItem ?: $award->rfqItem;
                $selectedQty = (float) ($award->awarded_quantity ?? 0);
                $unitPrice = (float) ($offerItem?->unit_price ?? 0);

                if (! $offer || ! $seller || ! $rfqItem) {
                    return null;
                }

                return [
                    'offer_id' => $offer->id,
                    'line_no' => (int) ($rfqItem->line_no ?? 0),
                    'product_name' => $rfqItem->product_name ?: '-',
                    'part_no' => $rfqItem->part_no ?: '',
                    'unit' => $rfqItem->unit ?: '',
                    'supplier_name' => $seller->company_name ?: $seller->name ?: '-',
                    'selected_qty' => $this->decimalString($selectedQty),
                    'unit_price' => $this->decimalString($unitPrice),
                    'line_total' => $this->decimalString(round($selectedQty * $unitPrice, 2)),
                    'currency' => $offer->currency ?: $rfq->currency,
                    'buyer_note' => $award->buyer_note ?? '',
                ];
            })
            ->filter()
            ->values();

        return [
            'has_confirmed' => $rows->isNotEmpty(),
            'type' => 'spare_parts',
            'overall_selected_total' => $this->decimalString($rows->sum(fn (array $row) => (float) ($row['line_total'] ?? 0))),
            'rows' => $rows->all(),
        ];
    }

    private function selectedBuyerOrderOfferId(Request $request, Rfq $rfq): ?int
    {
        $offerId = (int) $request->query('offer', 0);

        if ($offerId <= 0) {
            return null;
        }

        $hasConfirmedAwardForOffer = OfferAward::query()
            ->where('rfq_id', $rfq->id)
            ->where('offer_id', $offerId)
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->exists();

        return $hasConfirmedAwardForOffer ? $offerId : null;
    }

    private function notifyBuyerAboutOfferSubmitted(Rfq $rfq, Offer $offer): void
    {
        $seller = $offer->seller;
        $rfq->buyer->notify(new MarketplaceNotification(
            [
                'tone' => 'info',
                'action_url' => $rfq->buyerShowUrl(),
                'en' => [
                    'subject' => 'Sea Requests | New Supplier Offer Received',
                    'title' => 'New Supplier Offer Received',
                    'message' => 'A supplier has submitted a new commercial offer for your RFQ.',
                    'details' => [
                        ['label' => 'Reference No', 'value' => $rfq->reference_no],
                        ['label' => 'Supplier', 'value' => $seller?->name ?? '-'],
                        ['label' => 'Currency', 'value' => $offer->currency],
                        ['label' => 'Grand Total', 'value' => $offer->grand_total],
                    ],
                    'action_label' => 'Review RFQ',
                ],
            ],
            ['mail', 'database']
        ));
    }

    private function notifySellerAboutOfferSubmitted(User $seller, Rfq $rfq, Offer $offer, bool $isUpdate): void
    {
        $seller->notify(new MarketplaceNotification(
            [
                'tone' => 'success',
                'action_url' => route('seller.offers.create', $rfq),
                'en' => [
                    'subject' => $isUpdate
                        ? 'Sea Requests | Offer Update Confirmed'
                        : 'Sea Requests | Offer Submission Confirmed',
                    'title' => $isUpdate ? 'Offer Update Confirmed' : 'Offer Submission Confirmed',
                    'message' => $isUpdate
                        ? 'Your updated RFQ offer has been saved and shared with the buyer.'
                        : 'Your RFQ offer has been submitted successfully and shared with the buyer.',
                    'details' => [
                        ['label' => 'Reference No', 'value' => $rfq->reference_no],
                        ['label' => 'Currency', 'value' => $offer->currency],
                        ['label' => 'Grand Total', 'value' => $offer->grand_total],
                    ],
                    'action_label' => 'Review Offer',
                ],
            ],
            ['mail', 'database']
        ));
    }

    private function notifyBuyerAboutAwardSaved(
        User $buyer,
        Rfq $rfq,
        string $intent,
        int $selectedSuppliers,
        int $selectedLines,
        float $overallTotal,
        string $currency
    ): void {
        $isConfirmed = $intent === 'confirm';
        $statusLabel = $isConfirmed ? 'Confirmed' : 'Draft';
        $statusTitleEn = $isConfirmed
            ? 'Sea Requests | Award Selection Confirmed'
            : 'Sea Requests | Award Draft Saved';
        $statusCardTitleEn = $isConfirmed ? 'Award Selection Confirmed' : 'Award Draft Saved';
        $statusMessageEn = $isConfirmed
            ? 'Your supplier award selections have been confirmed and moved to the next workflow stage.'
            : 'Your supplier award selections have been saved as a draft for further review.';

        $buyer->notify(new MarketplaceNotification(
            [
                'tone' => $isConfirmed ? 'success' : 'info',
                'action_url' => $rfq->buyerCompareUrl(),
                'en' => [
                    'subject' => $statusTitleEn,
                    'title' => $statusCardTitleEn,
                    'message' => $statusMessageEn,
                    'details' => [
                        ['label' => 'Reference No', 'value' => $rfq->reference_no],
                        ['label' => 'Request Type', 'value' => $this->requestTypeLabel($rfq)],
                        ['label' => 'Selection Status', 'value' => $statusLabel],
                        ['label' => 'Selected Suppliers', 'value' => (string) $selectedSuppliers],
                        ['label' => $rfq->request_type === 'service_request' ? 'Selected Offers' : 'Selected Lines', 'value' => (string) $selectedLines],
                        ['label' => 'Overall Selected Total', 'value' => $this->notificationMoney($currency, $overallTotal)],
                    ],
                    'action_label' => 'Review Award Summary',
                ],
            ],
            ['mail', 'database']
        ));
    }

    private function notifySellersAboutAwardSaved(Rfq $rfq, string $intent, $selectedSupplierGroups): void
    {
        $isConfirmed = $intent === 'confirm';

        foreach ($selectedSupplierGroups as $group) {
            $seller = $group['seller'] ?? null;

            if (! $seller instanceof User) {
                continue;
            }

            $linesLabel = $rfq->request_type === 'service_request' ? 'Selected Offer' : 'Selected Lines';

            $actionUrl = $isConfirmed && ! empty($group['offer_id'])
                ? route('seller.orders.show', ['offer' => (int) $group['offer_id']])
                : route('seller.rfqs.show', $rfq);

            $seller->notify(new MarketplaceNotification(
                [
                    'tone' => $isConfirmed ? 'success' : 'info',
                    'action_url' => $actionUrl,
                    'en' => [
                        'subject' => $isConfirmed
                            ? 'Sea Requests | Buyer Award Selection Confirmed'
                            : 'Sea Requests | Buyer Award Draft Saved',
                        'title' => $isConfirmed ? 'Buyer Award Selection Confirmed' : 'Buyer Award Draft Saved',
                        'message' => $isConfirmed
                            ? 'The buyer selected lines from your offer and confirmed this award selection.'
                            : 'The buyer selected lines from your offer and saved them as an award draft.',
                        'details' => [
                            ['label' => 'Reference No', 'value' => $rfq->reference_no],
                            ['label' => 'Request Type', 'value' => $this->requestTypeLabel($rfq)],
                            ['label' => 'Selection Status', 'value' => $isConfirmed ? 'Confirmed' : 'Draft'],
                            ['label' => $linesLabel, 'value' => (string) ($group['selected_lines'] ?? 0)],
                            ['label' => 'Selected Total', 'value' => $this->notificationMoney((string) ($group['currency'] ?? ''), (float) ($group['selected_total'] ?? 0))],
                        ],
                        'action_label' => $isConfirmed ? 'Open Order Detail' : 'Review Selection',
                    ],
                ],
                ['mail', 'database']
            ));
        }
    }

    private function notifyBuyerAboutRfqCreated(User $buyer, Rfq $rfq): void
    {
        $buyer->notify(new MarketplaceNotification(
            $this->buyerRfqNotificationContent(
                rfq: $rfq,
                actionUrl: $rfq->buyerShowUrl(),
                en: [
                    'subject' => 'Sea Requests | RFQ Successfully Created',
                    'title' => 'RFQ Successfully Created',
                    'message' => 'Your RFQ has been created successfully and routed to the selected suppliers.',
                    'action_label' => 'Review RFQ',
                ],
            ),
            ['mail', 'database']
        ));
    }

    private function notifyBuyerAboutRfqUpdated(User $buyer, Rfq $rfq): void
    {
        $buyer->notify(new MarketplaceNotification(
            $this->buyerRfqNotificationContent(
                rfq: $rfq,
                actionUrl: $rfq->buyerShowUrl(),
                en: [
                    'subject' => 'Sea Requests | RFQ Update Confirmed',
                    'title' => 'RFQ Update Confirmed',
                    'message' => 'The latest changes to your RFQ have been saved successfully.',
                    'action_label' => 'Review RFQ',
                ],
            ),
            ['mail', 'database']
        ));
    }

    private function notifyBuyerAboutRfqDeleted(User $buyer, Rfq $rfq): void
    {
        $buyer->notify(new MarketplaceNotification(
            [
                'tone' => 'info',
                'action_url' => route('buyer.requests'),
                'en' => [
                    'subject' => 'Sea Requests | RFQ Deleted',
                    'title' => 'RFQ Deleted',
                    'message' => 'Your RFQ has been removed from the system.',
                    'details' => [
                        ['label' => 'Reference No', 'value' => $rfq->reference_no],
                        ['label' => 'Request Type', 'value' => $this->requestTypeLabel($rfq)],
                        ['label' => 'Previous Status', 'value' => $this->buyerNotificationStatusLabel($rfq)],
                    ],
                    'action_label' => 'Open Buyer Dashboard',
                ],
            ],
            ['mail', 'database']
        ));
    }

    private function buyerRfqNotificationContent(Rfq $rfq, string $actionUrl, array $en): array
    {
        $details = [
            ['label' => 'Reference No', 'value' => $rfq->reference_no],
            ['label' => 'Request Type', 'value' => $this->requestTypeLabel($rfq)],
            ['label' => 'RFQ Status', 'value' => $this->buyerNotificationStatusLabel($rfq)],
            ['label' => 'Requisition Date', 'value' => optional($rfq->requisition_date)->format('Y-m-d') ?: '-'],
            ['label' => 'Due Date', 'value' => optional($rfq->due_date)->format('Y-m-d') ?: '-'],
        ];

        return [
            'tone' => 'info',
            'action_url' => $actionUrl,
            'en' => $en + ['details' => $details],
        ];
    }

    private function notificationMoney(string $currency, float $amount): string
    {
        $formatted = number_format($amount, 2, '.', ',');

        return trim(($currency !== '' ? $currency.' ' : '').$formatted);
    }

    private function requestTypeLabel(Rfq $rfq): string
    {
        return $rfq->request_type === 'service_request' ? 'Service Request' : 'Spare Parts Request';
    }

    private function buyerNotificationStatusLabel(Rfq $rfq): string
    {
        return match ($rfq->status) {
            Rfq::STATUS_SUBMITTED => 'Open',
            Rfq::STATUS_CLOSED => 'Close',
            Rfq::STATUS_CANCELLED => 'Cancelled',
            default => 'Draft',
        };
    }

    private function similarPublicRfqs(Rfq $rfq, int $limit = 10): array
    {
        $countryNames = collect($rfq->country_names ?? [])
            ->filter()
            ->values();

        $primary = Rfq::query()
            ->whereKeyNot($rfq->id)
            ->published()
            ->publicMarketplace()
            ->where('request_type', $rfq->request_type)
            ->select([
                'id',
                'reference_no',
                'request_type',
                'company_name',
                'country_name',
                'country_names',
                'requisition_date',
                'due_date',
                'priority',
                'service_title',
                'service_description',
                'status',
                'submitted_at',
                'updated_at',
            ])
            ->withCount('items')
            ->latest('updated_at')
            ->limit(24)
            ->get();

        $related = $primary
            ->sortByDesc(function (Rfq $candidate) use ($countryNames) {
                $candidateCountries = collect($candidate->country_names ?? [])->filter()->values();

                if ($countryNames->isEmpty() || $candidateCountries->isEmpty()) {
                    return 0;
                }

                return $candidateCountries->intersect($countryNames)->count();
            })
            ->values();

        if ($related->count() < $limit) {
            $fallback = Rfq::query()
                ->whereKeyNot($rfq->id)
                ->published()
                ->publicMarketplace()
                ->whereNotIn('id', $related->pluck('id')->all())
                ->select([
                    'id',
                    'reference_no',
                    'request_type',
                    'company_name',
                    'country_name',
                    'country_names',
                    'requisition_date',
                    'due_date',
                    'priority',
                    'service_title',
                    'service_description',
                    'status',
                    'submitted_at',
                    'updated_at',
                ])
                ->withCount('items')
                ->latest('updated_at')
                ->limit($limit - $related->count())
                ->get();

            $related = $related->concat($fallback)->values();
        }

        return $related
            ->take($limit)
            ->map(fn (Rfq $item) => $this->publicRequestCardData($item))
            ->values()
            ->all();
    }

    private function publicRequestCardData(Rfq $rfq): array
    {
        $visibility = $this->rfqAccess->visibilityPresentation($rfq);
        $itemCount = $rfq->items_count ?: $rfq->items_count_count;
        $companySeed = trim((string) ($rfq->company_name ?: $rfq->reference_no ?: 'REQ'));
        $companyMask = mb_substr($companySeed, 0, 3).'***';
        $title = $rfq->request_type === 'service_request'
            ? ($rfq->service_title ?: 'Service Request')
            : 'Spare Parts Request';
        $summary = $rfq->request_type === 'service_request'
            ? ($rfq->service_description ?: "{$companyMask} has published a service request.")
            : "A spare parts request for {$itemCount} products has been published by {$companyMask}. Review the details to submit your offer.";
        $displayCountry = collect($rfq->country_names ?? [])
            ->filter()
            ->values()
            ->implode(', ');

        return [
            'id' => $rfq->id,
            'reference_no' => $rfq->reference_no,
            'request_type' => $rfq->request_type,
            'country_name' => $rfq->country_name,
            'country_names' => collect($rfq->country_names ?? [])
                ->filter()
                ->values()
                ->all(),
            'requisition_date' => optional($rfq->requisition_date)->format('Y-m-d'),
            'due_date' => optional($rfq->due_date)->format('Y-m-d'),
            'priority' => $rfq->priority,
            'status' => $rfq->canReceiveSupplierResponses() ? 'live' : 'close',
            'items_count' => $itemCount,
            'service_title' => $rfq->service_title,
            'service_description' => $rfq->service_description,
            'visibility_scope' => $visibility['scope'],
            'is_private_request' => $visibility['is_private'],
            'visibility_badge' => $visibility['badge'],
            'visibility_note' => $visibility['index_note'],
            'show_url' => $rfq->publicShowUrl(),
            'submitted_at' => optional($rfq->submitted_at)?->toISOString(),
            'updated_at' => optional($rfq->updated_at)?->toISOString(),
            'href' => $rfq->publicShowUrl(),
            'primary_category' => [
                'name' => $rfq->request_type === 'service_request' ? 'Service Request' : 'Spare Parts',
                'slug' => $rfq->request_type,
            ],
            'secondary_category' => [
                'name' => $title,
                'slug' => Str::slug($title),
            ],
            'summary' => $summary,
            'company_mask' => $companyMask,
            'company_name' => $companyMask,
            'display_country' => $displayCountry !== '' ? $displayCountry : ($rfq->country_name ?: '-'),
        ];
    }

    private function requestDetailMeta(Rfq $rfq, array $visibility, bool $sellerWorkspace, bool $canViewCompanyShip): array
    {
        $isServiceRequest = $rfq->request_type === 'service_request';
        $titleBase = $visibility['is_private']
            ? 'Private Request'
            : ($isServiceRequest ? ($rfq->service_title ?: 'Service Request') : 'Spare Parts Request');

        $description = $isServiceRequest
            ? $this->serviceRequestMetaDescription($rfq)
            : $this->sparePartsRequestMetaDescription($rfq);

        $shouldIndex = ! $sellerWorkspace
            && $rfq->isPublicMarketplace()
            && ! $visibility['is_private']
            && ! $canViewCompanyShip;

        return [
            'title' => "{$titleBase} | {$rfq->reference_no}",
            'description' => $description,
            'canonical' => $sellerWorkspace ? route('seller.rfqs.show', $rfq) : $rfq->publicShowUrl(),
            'robots' => $shouldIndex ? 'index, follow' : 'noindex, nofollow',
            'ogImage' => asset(config('brand.assets.og_image', 'brand/sea-requests-og.png')),
            'twitterCard' => 'summary_large_image',
        ];
    }

    private function serviceRequestMetaDescription(Rfq $rfq): string
    {
        $rawDescription = trim(strip_tags((string) $rfq->service_description));

        if ($rawDescription !== '') {
            return Str::limit($rawDescription, 160);
        }

        $countrySummary = collect($rfq->country_names ?? [])
            ->filter()
            ->values()
            ->take(3)
            ->implode(', ');

        $fallback = 'Published marine service request on Sea Requests.';

        if ($countrySummary !== '') {
            $fallback = "Published marine service request covering {$countrySummary} on Sea Requests.";
        }

        return Str::limit($fallback, 160);
    }

    private function sparePartsRequestMetaDescription(Rfq $rfq): string
    {
        $productNames = $rfq->items
            ->pluck('product_name')
            ->filter()
            ->values()
            ->take(3)
            ->implode(', ');

        $itemsCount = (int) ($rfq->items->count() ?: $rfq->items_count ?: $rfq->items_count_count ?: 0);

        if ($productNames !== '') {
            return Str::limit(
                "Published marine spare parts RFQ for {$productNames}. Review the request scope and supplier opportunity on Sea Requests.",
                160
            );
        }

        return Str::limit(
            "Published marine spare parts RFQ for {$itemsCount} requested items. Review the request scope and supplier opportunity on Sea Requests.",
            160
        );
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

    private function validatedSubcategoryIds($categoryIds, $subcategoryIds)
    {
        $validSubcategoryIds = Subcategory::query()
            ->whereIn('id', $subcategoryIds->all())
            ->whereIn('category_id', $categoryIds->all())
            ->pluck('id');

        return $subcategoryIds
            ->filter(fn (int $id) => $validSubcategoryIds->contains($id))
            ->values();
    }

    private function matchingSupplierListings($countries, $selectedPorts, array $selectedCountryCodes, $categoryIds, $subcategoryIds, $brandIds = null, $candidateIds = null, ?int $limit = null)
    {
        $query = SupplierServiceListing::query()
            ->visible()
            ->with('ports')
            ->when($candidateIds && $candidateIds->isNotEmpty(), fn ($builder) => $builder->whereIn('id', $candidateIds->all()))
            ->when($categoryIds->isNotEmpty(), fn ($builder) => $builder->whereIn('category_id', $categoryIds->all()))
            ->when($subcategoryIds->isNotEmpty(), fn ($builder) => $builder->whereIn('subcategory_id', $subcategoryIds->all()))
            ->when($brandIds && $brandIds->isNotEmpty(), function ($builder) use ($brandIds) {
                $builder->whereHas('seller', function ($sellerQuery) use ($brandIds) {
                    $sellerQuery->where(function ($brandScope) use ($brandIds) {
                        foreach ($brandIds as $brandId) {
                            $brandScope->orWhereJsonContains('service_brand_ids', (int) $brandId);
                        }
                    });
                });
            })
            ->when($countries->isNotEmpty(), function ($builder) use ($countries, $selectedCountryCodes) {
                $builder->where(function ($countryScope) use ($countries, $selectedCountryCodes) {
                    $countryScope
                        ->whereIn('country', $countries->all())
                        ->orWhereHas('ports', function ($portsQuery) use ($countries, $selectedCountryCodes) {
                            $portsQuery->where(function ($countryQuery) use ($countries, $selectedCountryCodes) {
                                $countryQuery->whereIn('country_name', $countries->all());

                                if ($selectedCountryCodes !== []) {
                                    $countryQuery->orWhereIn('country_code', $selectedCountryCodes);
                                }
                            });
                        });
                });
            })
            ->when($selectedPorts->isNotEmpty(), function ($builder) use ($selectedPorts) {
                $builder->whereHas('ports', function ($portsQuery) use ($selectedPorts) {
                    $portsQuery->where(function ($portScope) use ($selectedPorts) {
                        foreach ($selectedPorts as $port) {
                            $portScope->orWhere(function ($singlePortQuery) use ($port) {
                                $singlePortQuery->where('port_name', $port->port_name);

                                if ($port->unlocode) {
                                    $singlePortQuery->orWhere('unlocode', $port->unlocode);
                                }
                            });
                        }
                    });
                });
            })
            ->when($countries->isNotEmpty() && $selectedPorts->isNotEmpty(), function ($builder) use ($countries, $selectedCountryCodes) {
                $builder->whereHas('ports', function ($portsQuery) use ($countries, $selectedCountryCodes) {
                    $portsQuery->where(function ($countryQuery) use ($countries, $selectedCountryCodes) {
                        $countryQuery->whereIn('country_name', $countries->all());

                        if ($selectedCountryCodes !== []) {
                            $countryQuery->orWhereIn('country_code', $selectedCountryCodes);
                        }
                    });
                });
            })
            ->orderBy('company_name')
            ->orderBy('category_name')
            ->orderBy('subcategory_name');

        $suppliers = $query->get()
            ->unique(fn (SupplierServiceListing $listing) => $this->supplierDeliveryKey($listing->seller_id, $listing->company_name))
            ->values();

        if ($limit !== null) {
            return $suppliers->take($limit)->values();
        }

        return $suppliers;
    }
}
