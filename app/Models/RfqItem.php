<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqItem extends Model
{
    protected $fillable = [
        'rfq_id',
        'line_no',
        'product_name',
        'part_no',
        'quantity',
        'unit',
        'manufacturer',
        'model_type',
        'serial_number',
        'catalog_code',
        'rob',
        'drawing_number',
        'quality',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rob' => 'decimal:2',
            'line_no' => 'integer',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RfqItemAttachment::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(OfferAward::class);
    }
}
