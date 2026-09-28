<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Services\Meeting\LiveRecordingService;
use App\Services\Meeting\MeetingProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class FailStuckMeetings extends Command
{
    protected $signature = 'meetings:fail-stuck {--minutes=20 : Batas menit tanpa kemajuan sebelum rapat berstatus Memproses dianggap macet}';

    protected $description = 'Tandai Gagal rapat yang tidak menunjukkan kemajuan pemrosesan melebihi batas waktu (job AI gantung/timeout tidak tertangkap)';

    public function handle(MeetingProcessingService $processingService, LiveRecordingService $live): int
    {
        // Rekaman live yang tidak lagi menerima audio (laptop mati, tab tertutup dan
        // tidak dilanjutkan): jangan dibuang — proses rekaman yang sudah masuk.
        $staleLive = Meeting::where('status', 'Berlangsung')
            ->where('processing_heartbeat_at', '<=', now()->subMinutes((int) Config::get('ai.live.stale_minutes', 15)))
            ->get();
        foreach ($staleLive as $meeting) {
            $live->stop($meeting, null, 'tidak ada audio masuk selama '.Config::get('ai.live.stale_minutes', 15).' menit');
            Log::warning("Rekaman live Meeting ID {$meeting->id} terputus, dihentikan otomatis oleh watchdog.");
        }

        $minutes = (int) $this->option('minutes');
        $threshold = now()->subMinutes($minutes);

        // Yang diukur adalah kemajuan terakhir (heartbeat disentuh tiap potongan
        // rekaman selesai), bukan total durasi — rekaman 3 jam yang terus maju
        // tidak boleh dianggap macet.
        $stuckMeetings = Meeting::where('status', 'Memproses')
            ->where(fn ($q) => $q->where('processing_heartbeat_at', '<=', $threshold)
                ->orWhere(fn ($q) => $q->whereNull('processing_heartbeat_at')->where('updated_at', '<=', $threshold)))
            ->get();

        $failed = 0;
        foreach ($stuckMeetings as $meeting) {
            if ($processingService->markFailed($meeting, "tidak ada kemajuan selama lebih dari {$minutes} menit, ditandai Gagal otomatis oleh watchdog.")) {
                $failed++;
                Log::warning("Meeting ID {$meeting->id} macet di status Memproses, ditandai Gagal oleh watchdog.");
            }
        }

        $this->info("{$failed} meeting macet ditandai Gagal.");

        return self::SUCCESS;
    }
}
