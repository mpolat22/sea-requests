<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqSupplierRecipient extends Model
{
    protected $fillable = [
        'rfq_id',
        'supplier_service_listing_id',
        'seller_id',
        'company_name',
        'category_name',
        'subcategory_name',
        'country_name',
        'port_name',
        'delivery_status',
        'queued_at',
        'delivered_at',
        'failed_at',
        'delivery_error',
        'delivery_attempts',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'delivery_attempts' => 'integer',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(SupplierServiceListing::class, 'supplier_service_listing_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
