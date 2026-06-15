<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferInvoice extends Model
{
    public const STATUS_INVOICE_UPLOADED = 'invoice_uploaded';

    public const STATUS_PAYMENT_PROOF_UPLOADED = 'payment_proof_uploaded';

    public const STATUS_PAYMENT_CONFIRMED = 'payment_confirmed';

    protected $fillable = [
        'offer_id',
        'currency',
        'invoice_number',
        'invoice_date',
        'invoice_amount',
        'invoice_notes',
        'invoice_document_disk',
        'invoice_document_path',
        'invoice_document_name',
        'invoice_document_mime_type',
        'invoice_document_size',
        'payment_proof_date',
        'payment_reference',
        'payment_notes',
        'payment_proof_document_disk',
        'payment_proof_document_path',
        'payment_proof_document_name',
        'payment_proof_document_mime_type',
        'payment_proof_document_size',
        'payment_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'invoice_amount' => 'decimal:2',
            'payment_proof_date' => 'date',
            'payment_confirmed_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function hasInvoiceDocument(): bool
    {
        return filled($this->invoice_document_path);
    }

    public function hasPaymentProof(): bool
    {
        return filled($this->payment_proof_document_path);
    }

    public function hasBuyerPaymentSubmission(): bool
    {
        return $this->hasPaymentProof()
            || $this->payment_proof_date !== null
            || filled($this->payment_reference)
            || filled($this->payment_notes);
    }

    public function isPaymentConfirmed(): bool
    {
        return $this->payment_confirmed_at !== null;
    }

    public function status(): string
    {
        if ($this->isPaymentConfirmed()) {
            return self::STATUS_PAYMENT_CONFIRMED;
        }

        if ($this->hasBuyerPaymentSubmission()) {
            return self::STATUS_PAYMENT_PROOF_UPLOADED;
        }

        return self::STATUS_INVOICE_UPLOADED;
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            self::STATUS_PAYMENT_PROOF_UPLOADED => 'Payment Proof Uploaded',
            self::STATUS_PAYMENT_CONFIRMED => 'Payment Received Confirmed',
            default => 'Invoice Uploaded',
        };
    }

    public function canBuyerManagePaymentProof(): bool
    {
        return ! $this->isPaymentConfirmed() && $this->hasInvoiceDocument();
    }

    public function canSellerConfirmPayment(): bool
    {
        return ! $this->isPaymentConfirmed() && $this->hasBuyerPaymentSubmission();
    }
}
