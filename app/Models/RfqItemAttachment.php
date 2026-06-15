<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqItemAttachment extends Model
{
    protected $fillable = [
        'rfq_item_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class, 'rfq_item_id');
    }
}
