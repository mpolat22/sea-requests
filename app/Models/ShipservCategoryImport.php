<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipservCategoryImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_batch',
        'shipserv_external_id',
        'name',
        'normalized_name',
        'slug',
        'normalized_slug',
        'letter',
        'content_type',
        'suggestion_type',
        'suggested_parent_name',
        'suggested_parent_slug',
        'suggested_parent_source',
        'suggestion_confidence',
        'suggestion_rule',
        'source_url',
        'source_path',
        'discovered_on',
        'is_featured',
        'mapping_status',
        'publish_status',
        'category_id',
        'subcategory_id',
        'mapping_notes',
        'raw_payload',
        'imported_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'raw_payload' => 'array',
            'suggestion_confidence' => 'integer',
            'imported_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }
}
