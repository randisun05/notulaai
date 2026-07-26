<?php

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
    ];

    /**
     * Get the user that owns the meeting.
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

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('created_at', 'desc');
    }
}
