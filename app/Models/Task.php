<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public const STATUSES = ['Todo', 'In Progress', 'Waiting', 'Review', 'Done', 'Cancelled'];
    public const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];

    protected $fillable = [
        'meeting_id',
        'meeting_action_item_id',
        'unit_id',
        'assignee_id',
        'created_by',
        'title',
        'description',
        'assignee_name',
        'priority',
        'status',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(MeetingActionItem::class, 'meeting_action_item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('created_at', 'desc');
    }

    public function dispositions(): HasMany
    {
        return $this->hasMany(TaskDisposition::class)->orderBy('created_at', 'desc');
    }
}
