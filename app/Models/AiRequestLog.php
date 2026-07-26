<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequestLog extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'type',
        'provider',
        'model',
        'prompt',
        'response',
        'prompt_tokens',
        'completion_tokens',
        'cost',
        'duration_ms',
        'status',
        'error_message',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
