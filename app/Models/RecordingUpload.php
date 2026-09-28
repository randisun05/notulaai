<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordingUpload extends Model
{
    use HasUuids;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'file_name',
        'file_size',
        'received_bytes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'received_bytes' => 'integer',
    ];

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** Path file sementara di disk `local`. */
    public function partialPath(): string
    {
        return "recording_uploads/{$this->id}.part";
    }

    public function isComplete(): bool
    {
        return $this->received_bytes >= $this->file_size;
    }
}
