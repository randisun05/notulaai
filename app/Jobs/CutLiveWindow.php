<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Services\Meeting\LiveRecordingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Potong jendela rekaman live berikutnya di jeda hening. Unik per rapat: potongan
 * audio yang masuk tiap 5 detik tidak menumpuk job yang sama di antrean.
 */
class CutLiveWindow implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    public $backoff = [10, 30];

    public int $uniqueFor = 300;

    public function __construct(public Meeting $meeting) {}

    public function uniqueId(): string
    {
        return (string) $this->meeting->id;
    }

    public function handle(LiveRecordingService $live): void
    {
        $live->cut($this->meeting);
    }
}
