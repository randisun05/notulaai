<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendance extends Model
{
    public const METHOD_QR = 'qr';

    public const METHOD_MANUAL = 'manual';

    protected $fillable = ['meeting_id', 'user_id', 'name', 'position', 'organization', 'method', 'checked_in_at'];

    protected $casts = ['checked_in_at' => 'datetime'];

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

    /** "Nama, Jabatan, Instansi" — untuk konteks AI dan daftar peserta. */
    public function describe(): string
    {
        return collect([$this->name, $this->position, $this->organization])->filter()->implode(', ');
    }
}
