<?php

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use ScopedToUnit;

    public const STATUSES = ['Todo', 'In Progress', 'Waiting', 'Review', 'Done', 'Cancelled'];
    public const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
    private const CLOSED_STATUSES = ['Done', 'Cancelled'];

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
        'sla_hours',
        'escalated_at',
    ];

    protected $casts = [
        'deadline' => 'date',
        'escalated_at' => 'datetime',
    ];

    protected $appends = ['is_overdue', 'is_sla_breached'];

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

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->deadline || in_array($this->status, self::CLOSED_STATUSES, true)) {
            return false;
        }

        return $this->deadline->copy()->endOfDay()->isPast();
    }

    public function getIsSlaBreachedAttribute(): bool
    {
        if (!$this->sla_hours || in_array($this->status, self::CLOSED_STATUSES, true)) {
            return false;
        }

        return $this->created_at->copy()->addHours($this->sla_hours)->isPast();
    }
}
