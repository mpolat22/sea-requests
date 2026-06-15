<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierServiceListingPort extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'supplier_service_listing_id',
        'country_code',
        'country_name',
        'port_name',
        'unlocode',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(SupplierServiceListing::class, 'supplier_service_listing_id');
    }
}
