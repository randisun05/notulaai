<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Services\Meeting\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FailStuckMeetings extends Command
{
    protected $signature = 'meetings:fail-stuck {--minutes=10 : Batas menit sebuah meeting boleh macet di status Memproses}';

    protected $description = 'Tandai Gagal meeting yang macet di status Memproses melebihi batas waktu (job AI gantung/timeout tidak tertangkap)';

    public function handle(ActivityLogger $activityLogger): int
    {
        $minutes = (int) $this->option('minutes');

        $stuckMeetings = Meeting::where('status', 'Memproses')
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->get();

        foreach ($stuckMeetings as $meeting) {
            $meeting->update(['status' => 'Gagal']);

            $message = "Pemrosesan notula macet lebih dari {$minutes} menit tanpa respons AI, ditandai Gagal otomatis oleh watchdog.";

            Log::warning("Meeting ID {$meeting->id} macet di status Memproses, ditandai Gagal oleh watchdog.");
            $activityLogger->log($meeting, null, 'meeting.failed', $message);
        }

        $this->info("{$stuckMeetings->count()} meeting macet ditandai Gagal.");

        return self::SUCCESS;
    }
}
