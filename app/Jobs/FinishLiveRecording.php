<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Services\Meeting\LiveRecordingService;
use App\Services\Meeting\MeetingProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Rekaman live dihentikan: potong sisa rekaman, lalu lanjut ke transkripsi &
 * finalisasi yang sama dengan rekaman upload.
 */
class FinishLiveRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    public $backoff = [10, 60];

    public function __construct(public Meeting $meeting) {}

    public function handle(LiveRecordingService $live, MeetingProcessingService $processing): void
    {
        $live->finish($this->meeting, $processing);
    }

    public function failed(Throwable $e): void
    {
        app(MeetingProcessingService::class)->markFailed($this->meeting, $e->getMessage());
    }
}
