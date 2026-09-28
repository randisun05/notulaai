<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penanda yang diklik notulis saat rapat live ("Keputusan" / "Tindak Lanjut"),
 * dengan posisi waktunya di rekaman. Wajib masuk ke rangkuman.
 */
class MeetingMarker extends Model
{
    public const TYPES = [
        'keputusan' => 'Keputusan',
        'tindak_lanjut' => 'Tindak Lanjut',
    ];

    protected $fillable = ['meeting_id', 'user_id', 'type', 'at_seconds', 'note'];

    protected $casts = ['at_seconds' => 'float'];

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timeLabel(): string
    {
        $seconds = (int) floor($this->at_seconds);

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public function describe(): string
    {
        return '['.$this->timeLabel().'] '.self::TYPES[$this->type].($this->note ? ': '.$this->note : '');
    }
}
