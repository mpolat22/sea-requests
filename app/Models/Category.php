<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'has_subcategories',
        'is_active',
        'sort_order',
        'source',
        'source_external_id',
        'source_url',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'has_subcategories' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class)->orderBy('sort_order')->orderBy('name');
    }
}
