<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachContact extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'email',
        'audience',
        'primary_segment_id',
        'organization_name',
        'source_name',
        'status',
        'next_template_step',
        'sent_count',
        'last_template_id',
        'last_sent_at',
        'last_result',
        'notes',
        'source_payload',
    ];

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
            'source_payload' => 'array',
        ];
    }

    public function primarySegment(): BelongsTo
    {
        return $this->belongsTo(OutreachSegment::class, 'primary_segment_id');
    }

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(OutreachSegment::class, 'outreach_contact_segment', 'outreach_contact_id', 'outreach_segment_id')
            ->withTimestamps();
    }

    public function lastTemplate(): BelongsTo
    {
        return $this->belongsTo(OutreachTemplate::class, 'last_template_id');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(OutreachSendLog::class, 'contact_id');
    }

    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
