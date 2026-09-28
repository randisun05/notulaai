<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingSegment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'meeting_id',
        'index',
        'start_seconds',
        'end_seconds',
        'audio_path',
        'status',
        'text',
        'error',
    ];

    protected $casts = [
        'start_seconds' => 'float',
        'end_seconds' => 'float',
    ];

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Label waktu "[HH:MM:SS]" untuk awal bagian ini di rekaman aslinya.
     */
    public function startLabel(): string
    {
        $seconds = (int) floor($this->start_seconds);

        return sprintf('[%02d:%02d:%02d]', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
