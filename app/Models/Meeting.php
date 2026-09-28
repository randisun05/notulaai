<?php

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Meeting extends Model
{
    use HasFactory, ScopedToUnit;

    protected $fillable = [
        'user_id',
        'unit_id', // Ditambahkan
        'title',
        'date',
        'agenda',
        'attendees',
        'status',
        'transcript',
        'summary',
        'source_file_path', // Ditambahkan di sini
        'source_disk',
        'processing_stage',
        'processing_total_segments',
        'processing_heartbeat_at',
        'live_user_id',
        'live_started_at',
        'live_part',
        'live_extension',
        'live_bytes',
        'live_part_offset_seconds',
        'live_recorded_seconds',
        'live_cursor_seconds',
        'speaker_names',
        'attendance_token',
        'attendance_closed_at',
    ];

    protected $casts = [
        'processing_heartbeat_at' => 'datetime',
        'live_started_at' => 'datetime',
        'live_part' => 'integer',
        'live_bytes' => 'integer',
        'live_part_offset_seconds' => 'float',
        'live_recorded_seconds' => 'float',
        'live_cursor_seconds' => 'float',
        'speaker_names' => 'array',
        'attendance_closed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the meeting.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan unit yang memiliki rapat ini.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<MeetingActionItem, $this> */
    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class)->orderBy('order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(MeetingChatMessage::class)->orderBy('created_at');
    }

    /**
     * Komentar forum tingkat atas (balasan diakses lewat relasi `replies` masing-masing komentar).
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ForumComment::class)->whereNull('parent_id')->orderBy('created_at');
    }

    /**
     * Ringkasan progres untuk UI: status + tahap + jumlah potongan yang sudah ditranskrip.
     *
     * @return array{status: string, stage: ?string, done: int, total: ?int}
     */
    public function processingProgress(): array
    {
        return [
            'status' => $this->status,
            'stage' => $this->processing_stage,
            'done' => $this->processing_total_segments
                ? $this->segments()->where('status', MeetingSegment::STATUS_DONE)->count()
                : 0,
            'total' => $this->processing_total_segments,
        ];
    }

    /**
     * Data transkrip berjalan untuk halaman rapat (rekaman live), atau null kalau
     * rapat ini bukan/belum rekaman live.
     *
     * @return array<string, mixed>|null
     */
    public function liveState(User $viewer): ?array
    {
        if (! $this->live_started_at) {
            return null;
        }

        $segments = $this->segments()->get();
        $speakers = $segments
            ->flatMap(fn (MeetingSegment $segment) => preg_match_all('/^([^:\n]{1,60}):/mu', (string) $segment->text, $m) ? $m[1] : [])
            ->map(fn (string $label) => trim($label))
            ->unique()
            ->values();

        return [
            'is_recorder' => $this->live_user_id === $viewer->id,
            'recorded_seconds' => $this->live_recorded_seconds,
            'part' => $this->live_part,
            'segments' => $segments->map(fn (MeetingSegment $segment) => [
                'index' => $segment->index,
                'label' => trim($segment->startLabel(), '[]'),
                'status' => $segment->status,
                'text' => $segment->text === null ? null : $this->applySpeakerNames($segment->text),
            ]),
            'markers' => $this->markers()->with('user:id,name')->get()->map(fn (MeetingMarker $marker) => [
                'id' => $marker->id,
                'type' => $marker->type,
                'label' => $marker->timeLabel(),
                'note' => $marker->note,
                'user' => $marker->user?->name,
            ]),
            // Label mentah dari AI (mis. "Pembicara 1") + nama yang sudah ditetapkan.
            'speakers' => $speakers->map(fn (string $label) => ['label' => $label, 'name' => $this->speaker_names[$label] ?? null]),
        ];
    }

    /** @return HasMany<MeetingAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class)->orderBy('checked_in_at');
    }

    /** Token QR daftar hadir, dibuat saat pertama kali dibutuhkan. */
    public function attendanceToken(): string
    {
        if (! $this->attendance_token) {
            $this->forceFill(['attendance_token' => Str::random(40)])->save();
        }

        return $this->attendance_token;
    }

    public function isAttendanceOpen(): bool
    {
        return $this->attendance_closed_at === null;
    }

    /** @return HasOne<MeetingMinutes, $this> */
    public function minutes(): HasOne
    {
        return $this->hasOne(MeetingMinutes::class);
    }

    /** @return HasMany<MeetingMarker, $this> */
    public function markers(): HasMany
    {
        return $this->hasMany(MeetingMarker::class)->orderBy('at_seconds');
    }

    /**
     * Ganti label pembicara di awal baris ("Pembicara 1:") dengan nama yang
     * ditetapkan notulis (speaker_names).
     */
    public function applySpeakerNames(string $text): string
    {
        foreach ($this->speaker_names ?? [] as $label => $name) {
            $text = preg_replace('/^'.preg_quote((string) $label, '/').':/mu', addcslashes((string) $name, '\\$').':', $text);
        }

        return $text;
    }

    /** Path file bagian rekaman live ke-$part (disk `local`). */
    public function livePartPath(int $part): string
    {
        return "recordings/{$this->id}/live-{$part}.{$this->live_extension}";
    }

    /** @return HasMany<MeetingSegment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(MeetingSegment::class)->orderBy('index');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('created_at', 'desc');
    }
}
