<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferItem extends Model
{
    protected $fillable = [
        'offer_id',
        'rfq_item_id',
        'line_no',
        'offer_qty',
        'unit_price',
        'line_total',
        'delivery_time',
        'quality',
        'manufacturer',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'offer_qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OfferItemAttachment::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(OfferAward::class);
    }
}
