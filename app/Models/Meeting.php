<?php

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'processing_heartbeat_at' => 'datetime',
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
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

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
