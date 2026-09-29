<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Services\Meeting\DecisionService;
use Illuminate\Console\Command;

/**
 * Isi register keputusan untuk rapat yang diproses sebelum fitur ini ada.
 * Satu panggilan AI per rapat — jalankan bertahap dengan --limit.
 */
class ExtractMeetingDecisions extends Command
{
    protected $signature = 'meetings:extract-decisions {--limit=50 : Maksimal rapat yang diproses sekali jalan}';

    protected $description = 'Ekstrak keputusan (AI) untuk rapat yang sudah selesai diproses tapi belum punya daftar keputusan';

    public function handle(DecisionService $decisions): int
    {
        $meetings = Meeting::query()
            ->where('status', 'Selesai Diproses')
            ->whereDoesntHave('decisions')
            ->orderByDesc('date')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($meetings as $meeting) {
            $count = $decisions->extract($meeting);
            $this->line("#{$meeting->id} {$meeting->title}: {$count} keputusan");
        }

        $this->info("{$meetings->count()} rapat diproses.");

        return self::SUCCESS;
    }
}
