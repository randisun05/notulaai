<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
use Illuminate\Support\Facades\Config;
use Throwable;

class EmailDraftGenerator
{
    public const PURPOSES = [
        'follow_up' => 'Follow Up Rapat',
        'reminder' => 'Reminder Tindak Lanjut',
        'assignment' => 'Penugasan Action Item',
        'deadline_reminder' => 'Reminder Deadline',
    ];

    public function __construct(
        private readonly AiManager $ai,
        private readonly AiRequestLogger $logger,
        private readonly EmailDraftParser $parser,
    ) {}

    /**
     * @return array{subject: string, body: string}
     */
    public function generate(Meeting $meeting, string $purpose, ?User $requestedBy = null): array
    {
        if (! array_key_exists($purpose, self::PURPOSES)) {
            throw new \InvalidArgumentException("Tujuan email tidak dikenal: {$purpose}");
        }

        $prompt = $this->buildPrompt($meeting, $purpose);

        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('email_draft', $provider, $model, $prompt, $e->getMessage(), $meeting, $requestedBy);

            throw $e;
        }

        $this->logger->logSuccess('email_draft', $result->provider, $result->model, $prompt, $result->content, $result->promptTokens, $result->completionTokens, $result->durationMs, $meeting, $requestedBy);

        return $this->parser->parse($result->content, self::PURPOSES[$purpose].': '.$meeting->title);
    }

    private function buildPrompt(Meeting $meeting, string $purpose): string
    {
        $actionItemsText = $meeting->actionItems->isEmpty()
            ? 'Tidak ada action item yang tercatat.'
            : $meeting->actionItems->map(fn ($item) => sprintf(
                '- %s (PIC: %s, Deadline: %s)',
                $item->title,
                $item->assignee_name ?: 'belum ditentukan',
                $item->deadline?->toDateString() ?: 'belum ditentukan',
            ))->implode("\n");

        $instructions = match ($purpose) {
            'follow_up' => 'Buatkan draft email follow-up singkat dan profesional kepada peserta rapat, merangkum poin penting rapat dan mengingatkan tindak lanjut yang perlu dilakukan.',
            'reminder' => 'Buatkan draft email pengingat kepada peserta rapat mengenai tindak lanjut yang masih perlu dikerjakan dari rapat ini.',
            'assignment' => 'Buatkan draft email penugasan kepada masing-masing PIC action item, menjelaskan tugas dan deadline masing-masing dengan jelas.',
            'deadline_reminder' => 'Buatkan draft email pengingat deadline kepada PIC yang deadline action item-nya sudah dekat atau sudah lewat.',
        };

        return <<<PROMPT
        {$instructions}

        Balas HANYA dengan JSON valid berbentuk {"subject": "...", "body": "..."}, tanpa teks lain
        dan tanpa markdown code fence. Field "body" berisi HTML sederhana (paragraf <p>, list <ul><li>)
        dalam Bahasa Indonesia yang formal dan sopan.

        Judul Rapat: {$meeting->title}
        Tanggal: {$meeting->date}
        Rangkuman Rapat: {$meeting->summary}

        Daftar Action Item:
        {$actionItemsText}
        PROMPT;
    }
}
