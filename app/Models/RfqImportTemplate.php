<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqImportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'general_aliases',
        'item_aliases',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'general_aliases' => 'array',
            'item_aliases' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
