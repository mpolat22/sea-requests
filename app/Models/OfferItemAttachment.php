<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferItemAttachment extends Model
{
    protected $fillable = [
        'offer_item_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function offerItem(): BelongsTo
    {
        return $this->belongsTo(OfferItem::class);
    }
}
