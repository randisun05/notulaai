<?php

namespace App\Jobs;

use App\Mail\TaskEscalationNotice;
use App\Models\Task;
use App\Services\Meeting\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EscalateOverdueTasks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public function handle(ActivityLogger $activityLogger): void
    {
        Log::info('Menjalankan Job Eskalasi Task Terlambat...');

        $candidates = Task::whereNotIn('status', ['Done', 'Cancelled'])
            ->whereNull('escalated_at')
            ->with(['creator', 'meeting'])
            ->get()
            ->filter(fn (Task $task) => $task->is_overdue || $task->is_sla_breached);

        if ($candidates->isEmpty()) {
            Log::info('Tidak ada task yang perlu dieskalasi.');

            return;
        }

        foreach ($candidates as $task) {
            $reason = $task->is_overdue ? 'melewati deadline' : 'melampaui SLA yang ditetapkan';

            $task->update(['escalated_at' => now()]);

            $activityLogger->log(
                $task->meeting,
                null,
                'task.escalated',
                "Task \"{$task->title}\" dieskalasi otomatis karena {$reason}.",
                $task,
            );

            if (! empty($task->creator?->email)) {
                try {
                    Mail::to($task->creator->email)->send(new TaskEscalationNotice($task, $reason));
                    Log::info("Eskalasi Task ID {$task->id} terkirim ke {$task->creator->email}.");
                } catch (\Exception $e) {
                    Log::error("Gagal mengirim email eskalasi Task ID {$task->id}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Dipanggil Laravel setelah percobaan terakhir ($tries) tetap gagal.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Job EscalateOverdueTasks gagal permanen: '.$e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
}
