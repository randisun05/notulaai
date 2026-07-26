<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingActionItem extends Model
{
    protected $fillable = [
        'meeting_id',
        'title',
        'assignee_name',
        'deadline',
        'converted_to_task',
        'order',
    ];

    protected $casts = [
        'deadline' => 'date',
        'converted_to_task' => 'boolean',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }
}
