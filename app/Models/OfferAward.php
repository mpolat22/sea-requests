<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferAward extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'rfq_id',
        'buyer_id',
        'offer_id',
        'offer_item_id',
        'rfq_item_id',
        'request_type',
        'status',
        'awarded_quantity',
        'buyer_note',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'awarded_quantity' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function offerItem(): BelongsTo
    {
        return $this->belongsTo(OfferItem::class);
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class);
    }
}
