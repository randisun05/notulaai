<?php

namespace App\Services\Task;

use App\Models\Task;
use App\Models\User;
use App\Services\Meeting\ActivityLogger;
use App\Services\Webhook\WebhookDispatcher;

/**
 * Aturan perubahan status Task lewat dropdown/Kanban/API — dipakai bersama
 * TaskController dan Api\TaskController supaya aturannya tidak bercabang.
 */
class TaskStatusService
{
    /**
     * "Done" hanya lewat approve() (persetujuan), "Review" hanya lewat
     * submitForReview() (wajib bukti) — sisanya transisi kerja biasa.
     */
    public const SELECTABLE_STATUSES = ['Todo', 'In Progress', 'Waiting', 'Cancelled'];

    public const INVALID_STATUS_MESSAGE = 'Status "Done" hanya bisa dicapai lewat persetujuan, dan "Review" hanya bisa diajukan lewat "Ajukan untuk Review" dengan bukti pengerjaan.';

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly WebhookDispatcher $webhookDispatcher,
    ) {}

    /**
     * Pesan penolakan kalau $user tidak boleh mengubah status $task saat ini, atau null.
     * Task Done sudah disetujui; membukanya kembali hanya boleh oleh admin unit
     * tsb (TaskPolicy::manage), bukan siapa saja yang boleh update.
     */
    public function denialReason(Task $task, User $user): ?string
    {
        if ($task->status === 'Done' && ! $user->can('manage', $task)) {
            return 'Task yang sudah selesai hanya bisa dibuka kembali oleh admin.';
        }

        return null;
    }

    public function change(Task $task, string $status, User $user): void
    {
        $oldStatus = $task->status;
        $task->update(['status' => $status]);

        $this->activityLogger->log($task->meeting, $user, 'task.status_changed', "{$user->name} mengubah status Task \"{$task->title}\" dari {$oldStatus} menjadi {$status}.", $task);
        $this->webhookDispatcher->dispatch('task.status_changed', $task, ['task_id' => $task->id, 'title' => $task->title, 'old_status' => $oldStatus, 'new_status' => $status]);
    }
}
