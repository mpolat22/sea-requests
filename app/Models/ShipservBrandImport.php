<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipservBrandImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_batch',
        'shipserv_external_id',
        'name',
        'slug',
        'letter',
        'source_url',
        'source_path',
        'discovered_on',
        'is_featured',
        'mapping_status',
        'brand_id',
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
            'imported_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
