<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Services\Meeting\MeetingProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Setelah semua potongan tertranskrip: gabungkan, rangkum, buat action items.
 */
class FinalizeMeetingNotula implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 2;

    public $backoff = [60];

    public function __construct(public Meeting $meeting) {}

    public function handle(MeetingProcessingService $processor): void
    {
        $processor->finalizeFromSegments($this->meeting);
    }

    public function failed(Throwable $e): void
    {
        Log::error("Finalisasi notula Rapat ID {$this->meeting->id} gagal: {$e->getMessage()}");

        app(MeetingProcessingService::class)->markFailed($this->meeting, $e->getMessage());
    }
}
