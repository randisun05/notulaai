<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notulen resmi rapat (format dinas). Draf → Diajukan → Disahkan, atau
 * Dikembalikan ke notulis untuk diperbaiki. Tindak lanjut tidak disimpan di
 * sini: diambil dari action items rapat supaya tidak ada dua daftar yang berbeda.
 */
class MeetingMinutes extends Model
{
    public const STATUS_DRAFT = 'draf';

    public const STATUS_SUBMITTED = 'diajukan';

    public const STATUS_APPROVED = 'disahkan';

    public const STATUS_RETURNED = 'dikembalikan';

    protected $fillable = [
        'meeting_id', 'number', 'title', 'location', 'time_range', 'chairperson_id', 'chairperson_name', 'chairperson_title',
        'minute_taker_id', 'attendees', 'agenda', 'resume', 'decisions', 'closing', 'documentation',
        'status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'return_note',
    ];

    protected $casts = [
        'resume' => 'array',
        'decisions' => 'array',
        'documentation' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function chairperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chairperson_id');
    }

    /** @return BelongsTo<User, $this> */
    public function minuteTaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'minute_taker_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    public const DEFAULT_CLOSING = 'Demikian yang dapat disampaikan, terima kasih.';

    public function chairpersonDisplayName(): ?string
    {
        return $this->chairperson->name ?? $this->chairperson_name;
    }

    /**
     * Isi baris "Pemimpin Rapat" di dokumen: jabatan kalau ada (mis. "Direktur JF MASN"),
     * seperti kebiasaan notula dinas; nama tetap disebut di Resume.
     */
    public function chairpersonLine(): ?string
    {
        return $this->chairperson_title ?: $this->chairpersonDisplayName();
    }
}
