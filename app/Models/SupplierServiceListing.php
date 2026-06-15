<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierServiceListing extends Model
{
    protected $fillable = [
        'seller_id',
        'listing_key',
        'company_name',
        'contact_name',
        'country',
        'summary',
        'logo_path',
        'category_id',
        'category_name',
        'category_slug',
        'subcategory_id',
        'subcategory_name',
        'subcategory_slug',
        'vendor_slug',
        'search_text',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'subcategory_id' => 'integer',
            'seller_id' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function ports(): HasMany
    {
        return $this->hasMany(SupplierServiceListingPort::class, 'supplier_service_listing_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }
}
