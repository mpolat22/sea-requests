<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferMessage extends Model
{
    protected $fillable = [
        'offer_id',
        'sender_id',
        'body',
        'attachment_disk',
        'attachment_path',
        'attachment_name',
        'attachment_mime_type',
        'attachment_size',
    ];

    protected function casts(): array
    {
        return [
            'attachment_size' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
