<?php

namespace App\Jobs;

use App\Mail\TaskDeadlineReminder;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTaskDeadlineReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public function handle(): void
    {
        Log::info('Menjalankan Job Pengingat Deadline Task...');

        $dueTomorrow = Task::whereNotIn('status', ['Done', 'Cancelled'])
            ->whereDate('deadline', now()->addDay()->toDateString())
            ->whereNotNull('assignee_id')
            ->with('assignee')
            ->get();

        if ($dueTomorrow->isEmpty()) {
            Log::info('Tidak ada task yang jatuh tempo besok.');

            return;
        }

        foreach ($dueTomorrow as $task) {
            if (empty($task->assignee?->email)) {
                continue;
            }

            try {
                Mail::to($task->assignee->email)->send(new TaskDeadlineReminder($task));
                Log::info("Pengingat deadline Task ID {$task->id} terkirim ke {$task->assignee->email}.");
            } catch (\Exception $e) {
                Log::error("Gagal mengirim pengingat deadline Task ID {$task->id}: ".$e->getMessage());
            }
        }
    }

    /**
     * Dipanggil Laravel setelah percobaan terakhir ($tries) tetap gagal.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Job SendTaskDeadlineReminders gagal permanen: '.$e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
}
