<?php

namespace App\Services\Notification;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes in-app notifications for the bell in the navbar. Direct calls from
 * the same places that send e-mail / log activities (no events), like
 * ActivityLogger. Never notifies the actor about their own action, and never
 * lets a notification failure break the action that triggered it.
 */
class InAppNotifier
{
    public const KINDS = [
        'mention', 'task_assigned', 'task_review_requested', 'task_approved', 'task_rejected',
        'task_deadline', 'task_escalated', 'meeting_processed', 'meeting_failed',
    ];

    public function mentioned(User $user, Meeting $meeting, User $actor, string $commentBody): void
    {
        $this->send($user, 'mention', "{$actor->name} menyebut Anda di \"{$meeting->title}\"",
            Str::limit(html_entity_decode(strip_tags($commentBody)), 140), route('meetings.show', $meeting).'#forum', $actor);
    }

    public function taskAssigned(Task $task, ?User $actor): void
    {
        if ($task->assignee) {
            $this->send($task->assignee, 'task_assigned', "Task baru untuk Anda: \"{$task->title}\"",
                $task->deadline ? 'Deadline '.$task->deadline->locale('id')->translatedFormat('j M Y') : null, route('tasks.show', $task), $actor);
        }
    }

    /**
     * Admins of the task's unit approve it; superadmins only when the unit has
     * no admin, so they are not flooded with every unit's reviews.
     */
    public function reviewRequested(Task $task, User $actor): void
    {
        $approvers = User::role('admin')->where('unit_id', $task->unit_id)->get();
        if ($approvers->isEmpty()) {
            $approvers = User::role('superadmin')->get();
        }

        $this->send($approvers, 'task_review_requested', "{$actor->name} mengajukan review: \"{$task->title}\"",
            'Perlu persetujuan Anda.', route('tasks.show', $task), $actor);
    }

    public function reviewDecided(Task $task, User $actor, bool $approved, ?string $reason = null): void
    {
        if (! $task->assignee) {
            return;
        }

        $approved
            ? $this->send($task->assignee, 'task_approved', "Task disetujui: \"{$task->title}\"", "{$actor->name} menandai task ini selesai.", route('tasks.show', $task), $actor)
            : $this->send($task->assignee, 'task_rejected', "Task dikembalikan: \"{$task->title}\"", $reason ? "Alasan: {$reason}" : "{$actor->name} meminta perbaikan.", route('tasks.show', $task), $actor);
    }

    public function deadlineTomorrow(Task $task): void
    {
        if ($task->assignee) {
            $this->send($task->assignee, 'task_deadline', "Deadline besok: \"{$task->title}\"", null, route('tasks.show', $task));
        }
    }

    public function escalated(Task $task, string $reason): void
    {
        if ($task->creator) {
            $this->send($task->creator, 'task_escalated', "Task dieskalasi: \"{$task->title}\"", "Task ini {$reason}.", route('tasks.show', $task));
        }
    }

    public function meetingProcessed(Meeting $meeting): void
    {
        if ($meeting->creator) {
            $this->send($meeting->creator, 'meeting_processed', "Notula siap: \"{$meeting->title}\"", 'Rangkuman dan action items sudah dibuat AI.', route('meetings.show', $meeting));
        }
    }

    public function meetingFailed(Meeting $meeting): void
    {
        if ($meeting->creator) {
            $this->send($meeting->creator, 'meeting_failed', "Pemrosesan gagal: \"{$meeting->title}\"", 'Silakan coba proses ulang.', route('meetings.show', $meeting));
        }
    }

    /** @param User|iterable<User> $users */
    public function send(User|iterable $users, string $kind, string $title, ?string $body, string $url, ?User $actor = null): void
    {
        $recipients = Collection::wrap($users instanceof User ? [$users] : $users)
            ->filter()
            ->reject(fn (User $user) => $actor && $user->is($actor))
            ->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new AppNotification($kind, Str::limit($title, 190), $body, $url, $actor?->name));
        } catch (Throwable $e) {
            Log::error("Gagal menyimpan notifikasi in-app [{$kind}]: ".$e->getMessage());
        }
    }
}
