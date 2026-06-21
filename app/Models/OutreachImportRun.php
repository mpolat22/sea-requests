<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachImportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'audience',
        'file_name',
        'stored_path',
        'status',
        'imported_by',
        'row_count',
        'processed_count',
        'new_contacts_count',
        'updated_contacts_count',
        'duplicate_emails_count',
        'skipped_count',
        'message',
        'summary',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
