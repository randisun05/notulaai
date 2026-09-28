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
use OpenAI\Exceptions\ErrorException;
use Throwable;

/**
 * Langkah pertama pemrosesan notula: teks/gambar langsung dirangkum di sini;
 * audio/video hanya dipecah, lalu dilanjutkan TranscribeMeetingSegment.
 *
 * Semua timeout job pemrosesan harus di bawah `retry_after` antrean
 * (config/queue.php), kalau tidak job yang masih jalan diambil ulang worker lain.
 */
class ProcessMeetingNotula implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $meeting;

    public $timeout = 900;

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

        app(MeetingProcessingService::class)->markFailed($this->meeting, $errorMessage);
    }
}
