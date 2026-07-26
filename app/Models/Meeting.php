<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Meeting extends Model
{
    use HasFactory;

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
}
