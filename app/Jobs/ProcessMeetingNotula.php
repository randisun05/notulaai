<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Services\Meeting\ActivityLogger;
use App\Services\Meeting\MeetingProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OpenAI\Exceptions\ErrorException;
use Throwable;

class ProcessMeetingNotula implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $meeting;

    public $timeout = 300; // 5 menit timeout

    public $tries = 3;

    public $backoff = [30, 90, 180];

    public function __construct(Meeting $meeting)
    {
        $this->meeting = $meeting;
    }

    public function handle(MeetingProcessingService $processor): void
    {
        $processor->process($this->meeting);
    }

    /**
     * Dipanggil Laravel setelah percobaan terakhir ($tries) tetap gagal.
     */
    public function failed(Throwable $e): void
    {
        $errorMessage = $e->getMessage();
        if ($e instanceof ErrorException) {
            $errorMessage = 'OpenAI API Error: '.$e->getMessage();
        }

        Log::error("Gagal memproses notula untuk Rapat ID {$this->meeting->id}", [
            'error_message' => $errorMessage,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->meeting->update(['status' => 'Gagal']);

        app(ActivityLogger::class)->log($this->meeting, null, 'meeting.failed', 'Pemrosesan notula gagal: '.$errorMessage);
    }
}
