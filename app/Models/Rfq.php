<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Support\RfqAccessService;
use App\Support\OfferOrderWorkflow;

class Rfq extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PUBLISHED_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    public const VISIBILITY_PUBLIC_MARKETPLACE = 'public_marketplace';

    public const VISIBILITY_PRIVATE_SUPPLIER = 'private_supplier';

    protected $fillable = [
        'buyer_id',
        'reference_no',
        'company_name',
        'ship_name',
        'imo_number',
        'request_type',
        'visibility_scope',
        'country_name',
        'port_name',
        'country_names',
        'ports_by_country',
        'category_ids',
        'subcategory_ids',
        'brand_ids',
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
    ];

    protected function casts(): array
    {
        return [
            'requisition_date' => 'date',
            'due_date' => 'date',
            'country_names' => 'array',
            'ports_by_country' => 'array',
            'category_ids' => 'array',
            'subcategory_ids' => 'array',
            'brand_ids' => 'array',
            'submitted_at' => 'datetime',
            'items_count' => 'integer',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class)->orderBy('line_no');
    }

    public function supplierRecipients(): HasMany
    {
        return $this->hasMany(RfqSupplierRecipient::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(OfferAward::class);
    }

    public function submittedOffers(): HasMany
    {
        return $this->hasMany(Offer::class)->where('status', Offer::STATUS_SUBMITTED);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RfqAttachment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', self::PUBLISHED_STATUSES);
    }

    public function scopePublicMarketplace(Builder $query): Builder
    {
        return $query->where('visibility_scope', self::VISIBILITY_PUBLIC_MARKETPLACE);
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function visibilityScope(): string
    {
        return $this->visibility_scope ?: self::VISIBILITY_PUBLIC_MARKETPLACE;
    }

    public function isPublicMarketplace(): bool
    {
        return $this->visibilityScope() === self::VISIBILITY_PUBLIC_MARKETPLACE;
    }

    public function isPrivateSupplierRequest(): bool
    {
        return $this->visibilityScope() === self::VISIBILITY_PRIVATE_SUPPLIER;
    }

    public function isPublished(): bool
    {
        return in_array($this->status, self::PUBLISHED_STATUSES, true);
    }

    public function isVisibleTo(?User $user): bool
    {
        return app(RfqAccessService::class)->canView($this, $user);
    }

    public function isClosed(): bool
    {
        return in_array($this->effectiveStatus(), [self::STATUS_CLOSED, self::STATUS_CANCELLED], true);
    }

    public function isOverdueForSupplierResponses(): bool
    {
        return $this->due_date !== null && $this->due_date->lt(today());
    }

    public function canReceiveSupplierResponses(): bool
    {
        return $this->effectiveStatus() === self::STATUS_SUBMITTED
            && ! $this->isClosed()
            && ! $this->isOverdueForSupplierResponses();
    }

    public function offersCount(): int
    {
        if ($this->getAttribute('offers_count') !== null) {
            return max(0, (int) $this->getAttribute('offers_count'));
        }

        return $this->submittedOffers()->count();
    }

    public function hasOffers(): bool
    {
        return $this->offersCount() > 0;
    }

    public function awardsCount(): int
    {
        if ($this->getAttribute('awards_count') !== null) {
            return max(0, (int) $this->getAttribute('awards_count'));
        }

        return $this->awards()->count();
    }

    public function hasAwardSelections(): bool
    {
        return $this->awardsCount() > 0;
    }

    public function confirmedAwardsCount(): int
    {
        if ($this->getAttribute('confirmed_awards_count') !== null) {
            return max(0, (int) $this->getAttribute('confirmed_awards_count'));
        }

        return $this->awards()->where('status', OfferAward::STATUS_CONFIRMED)->count();
    }

    public function hasConfirmedAwards(): bool
    {
        return $this->confirmedAwardsCount() > 0;
    }

    public function hasCompletedConfirmedOrders(): bool
    {
        if (! $this->hasConfirmedAwards()) {
            return false;
        }

        $confirmedOfferIds = collect(
            $this->relationLoaded('awards')
                ? $this->awards
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->pluck('offer_id')
                    ->all()
                : $this->awards()
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->pluck('offer_id')
                    ->all()
        )
            ->filter()
            ->map(fn ($offerId) => (int) $offerId)
            ->unique()
            ->values();

        if ($confirmedOfferIds->isEmpty()) {
            return false;
        }

        $offers = $this->relationLoaded('offers')
            ? $this->offers
                ->whereIn('id', $confirmedOfferIds->all())
                ->values()
            : Offer::query()
                ->whereIn('id', $confirmedOfferIds->all())
                ->with('invoices')
                ->get();

        if ($offers->count() !== $confirmedOfferIds->count()) {
            return false;
        }

        $workflow = app(OfferOrderWorkflow::class);

        return $offers->every(
            fn (Offer $offer) => $workflow->resolveStatus($offer) === Offer::ORDER_STATUS_COMPLETED
        );
    }

    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_SUBMITTED && $this->hasConfirmedAwards()) {
            return self::STATUS_CLOSED;
        }

        return $this->status;
    }

    public function buyerDashboardStatus(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        if ($this->hasCompletedConfirmedOrders()) {
            return 'completed';
        }

        if ($this->hasConfirmedAwards()) {
            return 'award_confirmed';
        }

        if ($this->hasAwardSelections()) {
            return 'award_in_progress';
        }

        if ($this->status === self::STATUS_DRAFT) {
            return self::STATUS_DRAFT;
        }

        return $this->effectiveStatus() === self::STATUS_SUBMITTED
            ? 'open'
            : self::STATUS_CLOSED;
    }

    public function supplierDashboardStatus(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        if ($this->hasCompletedConfirmedOrders()) {
            return 'completed';
        }

        if ($this->hasConfirmedAwards()) {
            return 'award_confirmed';
        }

        if ($this->status === self::STATUS_DRAFT) {
            return self::STATUS_DRAFT;
        }

        return $this->effectiveStatus() === self::STATUS_SUBMITTED
            ? 'open'
            : self::STATUS_CLOSED;
    }

    public function canBeFullyEdited(): bool
    {
        if ($this->hasAwardSelections()) {
            return false;
        }

        if ($this->status === self::STATUS_DRAFT) {
            return true;
        }

        if ($this->status === self::STATUS_CANCELLED || $this->isOverdueForSupplierResponses()) {
            return false;
        }

        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_CLOSED], true)
            && ! $this->hasOffers()
            && $this->status !== self::STATUS_CANCELLED;
    }

    public function canBeGeneralInfoEditedOnly(): bool
    {
        if ($this->hasAwardSelections()) {
            return false;
        }

        if ($this->status === self::STATUS_CANCELLED) {
            return false;
        }

        if ($this->isOverdueForSupplierResponses()) {
            return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_CLOSED], true);
        }

        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_CLOSED], true)
            && $this->hasOffers()
            && $this->status !== self::STATUS_CANCELLED;
    }

    public function canBeEdited(): bool
    {
        return $this->canBeFullyEdited() || $this->canBeGeneralInfoEditedOnly();
    }

    public function canBeDeleted(): bool
    {
        if ($this->status === self::STATUS_DRAFT) {
            return true;
        }

        return $this->status === self::STATUS_CLOSED && ! $this->hasOffers();
    }

    public function editReason(): ?string
    {
        if ($this->hasAwardSelections()) {
            return 'award_started';
        }

        if ($this->isOverdueForSupplierResponses() && $this->canBeGeneralInfoEditedOnly()) {
            return 'overdue_extendable';
        }

        if ($this->canBeGeneralInfoEditedOnly()) {
            return 'offers_received';
        }

        if ($this->canBeFullyEdited()) {
            return $this->status === self::STATUS_CLOSED ? 'reopenable_closed' : null;
        }

        if ($this->status === self::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($this->isOverdueForSupplierResponses()) {
            return 'overdue';
        }

        return 'locked';
    }

    public function publicSlug(): string
    {
        $base = $this->request_type === 'service_request'
            ? ($this->service_title ?: 'service request')
            : 'spare parts request';

        $reference = $this->reference_no ?: 'rfq '.$this->id;

        return Str::slug(trim($base.' '.$reference));
    }

    public function publicShowUrl(): string
    {
        return route('rfqs.show', [
            'rfq' => $this->id,
            'slug' => $this->publicSlug(),
        ]);
    }

    public function buyerShowUrl(): string
    {
        return route('buyer.rfqs.show', $this);
    }

    public function buyerCompareUrl(): string
    {
        return route('buyer.rfqs.compare', $this);
    }
}
