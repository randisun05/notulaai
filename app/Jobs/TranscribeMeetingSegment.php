<?php

namespace App\Jobs;

use App\Models\MeetingSegment;
use App\Services\Meeting\MeetingProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Transkrip satu potongan (±10 menit) rekaman. Gagal permanen di satu potongan
 * menggagalkan rapatnya — transkrip yang bolong tidak layak dirangkum.
 */
class TranscribeMeetingSegment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 3;

    public $backoff = [30, 120];

    public function __construct(public MeetingSegment $segment) {}

    public function handle(MeetingProcessingService $processor): void
    {
        $processor->transcribeSegment($this->segment);
    }

    public function failed(Throwable $e): void
    {
        Log::error("Transkripsi bagian #{$this->segment->index} Rapat ID {$this->segment->meeting_id} gagal: {$e->getMessage()}");

        $this->segment->update(['status' => MeetingSegment::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000)]);

        if ($meeting = $this->segment->meeting) {
            $label = trim($this->segment->startLabel(), '[]');
            app(MeetingProcessingService::class)->markFailed($meeting, "transkripsi bagian mulai {$label} gagal: {$e->getMessage()}");
        }
    }
}
