<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachSendLog extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'contact_id',
        'segment_id',
        'schedule_id',
        'template_id',
        'sender_account_id',
        'cycle_key',
        'recipient_email',
        'recipient_organization',
        'sender_email',
        'status',
        'subject',
        'body_text',
        'queued_at',
        'attempted_at',
        'sent_at',
        'error_message',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'response_payload' => 'array',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(OutreachContact::class, 'contact_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(OutreachSegment::class, 'segment_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(OutreachSchedule::class, 'schedule_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OutreachTemplate::class, 'template_id');
    }

    public function senderAccount(): BelongsTo
    {
        return $this->belongsTo(OutreachSenderAccount::class, 'sender_account_id');
    }
}
