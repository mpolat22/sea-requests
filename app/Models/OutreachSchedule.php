<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'segment_id',
        'audience',
        'recurrence',
        'starts_on',
        'weekday',
        'suggested_start_time',
        'suggested_end_time',
        'uses_recommended_window',
        'start_time',
        'end_time',
        'send_interval_minutes',
        'is_active',
        'template_rotation',
        'last_dispatched_at',
        'last_cycle_key',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'uses_recommended_window' => 'boolean',
            'is_active' => 'boolean',
            'template_rotation' => 'array',
            'last_dispatched_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(OutreachSegment::class, 'segment_id');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(OutreachSendLog::class, 'schedule_id');
    }
}
