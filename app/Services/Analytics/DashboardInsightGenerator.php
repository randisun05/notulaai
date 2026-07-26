<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Collection;
use Throwable;

class DashboardInsightGenerator
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly AiRequestLogger $logger,
    ) {
    }

    /**
     * @param array<string, int> $stats
     * @param Collection<int, array{label: string, count: int}> $weeklyMeetings
     * @param Collection<int, array{status: string, count: int}> $taskStatusBreakdown
     */
    public function generate(array $stats, Collection $weeklyMeetings, Collection $taskStatusBreakdown, ?User $requestedBy = null): string
    {
        $prompt = $this->buildPrompt($stats, $weeklyMeetings, $taskStatusBreakdown);

        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('dashboard_insight', $provider, $model, $prompt, $e->getMessage(), null, $requestedBy);

            throw $e;
        }

        $this->logger->logSuccess(
            'dashboard_insight',
            $provider,
            $model,
            $prompt,
            $result->content,
            $result->promptTokens,
            $result->completionTokens,
            $result->durationMs,
            null,
            $requestedBy,
        );

        return $result->content;
    }

    private function buildPrompt(array $stats, Collection $weeklyMeetings, Collection $taskStatusBreakdown): string
    {
        $weeklyText = $weeklyMeetings->map(fn ($w) => "{$w['label']}: {$w['count']} rapat")->implode("\n");
        $statusText = $taskStatusBreakdown->map(fn ($s) => "{$s['status']}: {$s['count']}")->implode("\n");

        return <<<PROMPT
        Kamu adalah analis data yang membuat ringkasan insight singkat (maksimal 4-5 kalimat)
        dari data dashboard sebuah tim, dalam Bahasa Indonesia yang mudah dipahami manajemen.
        Fokus pada tren, area yang perlu perhatian (misalnya task overdue), dan hal positif yang
        patut diapresiasi. Jangan mengarang angka di luar data berikut ini.

        Statistik saat ini:
        - Rapat hari ini: {$stats['meetings_today']}
        - Rapat minggu ini: {$stats['meetings_this_week']}
        - Task selesai: {$stats['tasks_completed']}
        - Task overdue: {$stats['tasks_overdue']}
        - Progress keseluruhan: {$stats['progress_percent']}%

        Volume rapat 8 minggu terakhir:
        {$weeklyText}

        Distribusi status task:
        {$statusText}

        Tulis insight singkat berdasarkan data di atas.
        PROMPT;
    }
}
