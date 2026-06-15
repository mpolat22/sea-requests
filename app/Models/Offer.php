<?php

namespace App\Models;

use App\Support\OfferOrderWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const AWARD_SCOPE_PARTIAL_ALLOWED = 'partial_allowed';

    public const AWARD_SCOPE_FULL_SCOPE_REQUIRED = 'full_scope_required';

    public const ORDER_STATUS_ORDER_INFORMATION_PENDING = 'order_information_pending';

    public const ORDER_STATUS_INVOICE_PENDING = 'invoice_pending';

    public const ORDER_STATUS_INVOICE_UPLOADED = 'invoice_uploaded';

    public const ORDER_STATUS_BUYER_PAYMENT_PENDING = 'buyer_payment_pending';

    public const ORDER_STATUS_PAYMENT_PROOF_UPLOADED = 'payment_proof_uploaded';

    public const ORDER_STATUS_PAYMENT_CONFIRMED = 'payment_confirmed';

    public const ORDER_STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'rfq_id',
        'seller_id',
        'request_type',
        'currency',
        'status',
        'order_workflow_status',
        'billing_company_name',
        'billing_address',
        'billing_tax_id',
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
        'service_instruction_notes',
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
    ];

    protected function casts(): array
    {
        return [
            'including_tax' => 'boolean',
            'including_packing' => 'boolean',
            'including_freight' => 'boolean',
            'including_mobilization' => 'boolean',
            'tax_amount' => 'decimal:2',
            'packing_cost' => 'decimal:2',
            'freight_cost' => 'decimal:2',
            'mobilization_cost' => 'decimal:2',
            'total_offer_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'payment_order_confirmation' => 'decimal:2',
            'payment_before_shipment' => 'decimal:2',
            'payment_invoice_days' => 'integer',
            'delivery_required_date' => 'date',
            'service_required_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfferItem::class)->orderBy('line_no');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OfferAttachment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(OfferInvoice::class)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(OfferAward::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OfferMessage::class)->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(OfferMessage::class)->latestOfMany();
    }

    public function messageReads(): HasMany
    {
        return $this->hasMany(OfferMessageRead::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(SupplierReview::class);
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function awardScopePolicy(): string
    {
        return $this->award_scope_policy ?: self::AWARD_SCOPE_PARTIAL_ALLOWED;
    }

    /**
     * @return array<int, string>
     */
    public static function orderWorkflowStatuses(): array
    {
        return [
            self::ORDER_STATUS_ORDER_INFORMATION_PENDING,
            self::ORDER_STATUS_INVOICE_PENDING,
            self::ORDER_STATUS_INVOICE_UPLOADED,
            self::ORDER_STATUS_BUYER_PAYMENT_PENDING,
            self::ORDER_STATUS_PAYMENT_PROOF_UPLOADED,
            self::ORDER_STATUS_PAYMENT_CONFIRMED,
            self::ORDER_STATUS_COMPLETED,
        ];
    }

    public function orderWorkflowStatus(): ?string
    {
        return $this->order_workflow_status ?: null;
    }

    public function isOrderWorkflowCompleted(): bool
    {
        return app(OfferOrderWorkflow::class)->resolveStatus($this) === self::ORDER_STATUS_COMPLETED;
    }

    public function canBuyerEditOrderInformation(): bool
    {
        return ! $this->hasInvoices()
            && ! $this->isOrderWorkflowCompleted();
    }

    public function canSellerManageInvoices(): bool
    {
        return $this->hasCompleteOrderInformation()
            && ! $this->isOrderWorkflowCompleted();
    }

    public function canSellerManageInvoice(): bool
    {
        return $this->canSellerManageInvoices();
    }

    public function hasCompleteOrderInformation(): bool
    {
        $hasBillingInformation = filled($this->billing_company_name)
            && filled($this->billing_address)
            && filled($this->billing_contact_name)
            && filled($this->billing_contact_email)
            && filled($this->billing_contact_phone);

        if (! $hasBillingInformation) {
            return false;
        }

        if ($this->request_type === 'spare_parts') {
            return filled($this->delivery_target_type)
                && filled($this->delivery_country)
                && filled($this->delivery_port)
                && filled($this->delivery_address)
                && filled($this->delivery_contact_name)
                && filled($this->delivery_contact_email)
                && filled($this->delivery_contact_phone)
                && filled($this->delivery_required_date);
        }

        return filled($this->service_location_type)
            && filled($this->service_location)
            && filled($this->service_contact_name)
            && filled($this->service_contact_email)
            && filled($this->service_contact_phone)
            && filled($this->service_required_date);
    }

    public function hasInvoices(): bool
    {
        if ($this->relationLoaded('invoices')) {
            return $this->invoices->isNotEmpty();
        }

        return $this->invoices()->exists();
    }
}
