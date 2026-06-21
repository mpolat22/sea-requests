<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OutreachSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'audience',
        'region_key',
        'recommended_weekday',
        'recommended_start_time',
        'recommended_end_time',
        'is_active',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(OutreachContact::class, 'outreach_contact_segment', 'outreach_segment_id', 'outreach_contact_id')
            ->withTimestamps();
    }

    public function primaryContacts(): HasMany
    {
        return $this->hasMany(OutreachContact::class, 'primary_segment_id');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(OutreachSchedule::class, 'segment_id');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(OutreachSendLog::class, 'segment_id');
    }
}
