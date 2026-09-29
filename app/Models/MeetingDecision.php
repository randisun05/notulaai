<?php

namespace App\Models;

use App\Models\Concerns\ScopedToUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu keputusan rapat di register keputusan lintas rapat.
 */
class MeetingDecision extends Model
{
    use ScopedToUnit;

    public const SOURCE_AI = 'ai';

    public const SOURCE_MINUTES = 'notula';

    /** Filter status tindak lanjut di halaman Daftar Keputusan. */
    public const FOLLOW_UP_STATUSES = [
        'selesai' => 'Selesai',
        'berjalan' => 'Sedang dikerjakan',
        'belum' => 'Belum jadi Task',
        'tanpa' => 'Tanpa tindak lanjut',
    ];

    protected $fillable = ['meeting_id', 'unit_id', 'text', 'source', 'meeting_action_item_id', 'order'];

    protected $appends = ['follow_up'];

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<MeetingActionItem, $this> */
    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(MeetingActionItem::class, 'meeting_action_item_id');
    }

    /**
     * Status tindak lanjut keputusan ini, dari Task yang lahir dari action item-nya.
     *
     * @return array{status: string, label: string, task_id: ?int, task_status: ?string, title: ?string}
     */
    public function getFollowUpAttribute(): array
    {
        $item = $this->actionItem;
        $task = $item?->task;

        $status = match (true) {
            $item === null => 'tanpa',
            $task === null => 'belum',
            $task->status === 'Done' => 'selesai',
            $task->status === 'Cancelled' => 'tanpa',
            default => 'berjalan',
        };

        return [
            'status' => $status,
            'label' => $task?->status === 'Cancelled' ? 'Task dibatalkan' : self::FOLLOW_UP_STATUSES[$status],
            'task_id' => $task?->id,
            'task_status' => $task?->status,
            'title' => $item?->title,
        ];
    }
}
