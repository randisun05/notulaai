<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read Task|null $task Task yang dibuat dari action item ini (bila sudah dikonversi)
 */
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
        'deadline' => 'date:Y-m-d',
        'converted_to_task' => 'boolean',
    ];

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return HasOne<Task, $this> */
    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }
}
