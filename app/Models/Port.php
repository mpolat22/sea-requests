<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class Port extends Model
{
    protected $fillable = [
        'unlocode',
        'country_code',
        'location_code',
        'country_name',
        'port_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function sellers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seller_service_ports')
            ->withTimestamps();
    }
}
