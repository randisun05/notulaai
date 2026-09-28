<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use App\Models\MeetingChatMessage;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Throwable;

class MeetingChatService
{
    private const MAX_TRANSCRIPT_CHARS = 8000;

    private const MAX_HISTORY_MESSAGES = 10;

    public function __construct(
        private readonly AiManager $ai,
        private readonly AiRequestLogger $logger,
    ) {}

    public function ask(Meeting $meeting, string $question, ?User $user): MeetingChatMessage
    {
        // Prompt dibangun sebelum pertanyaan disimpan, supaya pertanyaan baru
        // tidak ikut muncul dua kali (di riwayat dan sebagai pertanyaan baru).
        $prompt = $this->buildPrompt($meeting, $question);

        MeetingChatMessage::create([
            'meeting_id' => $meeting->id,
            'user_id' => $user?->id,
            'role' => 'user',
            'content' => $question,
        ]);

        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('chat', $provider, $model, $prompt, $e->getMessage(), $meeting, $user);

            throw $e;
        }

        $this->logger->logSuccess('chat', $result->provider, $result->model, $prompt, $result->content, $result->promptTokens, $result->completionTokens, $result->durationMs, $meeting, $user);

        return MeetingChatMessage::create([
            'meeting_id' => $meeting->id,
            'user_id' => null,
            'role' => 'assistant',
            'content' => $result->content,
        ]);
    }

    private function buildPrompt(Meeting $meeting, string $question): string
    {
        $transcript = Str::limit($meeting->transcript ?? '', self::MAX_TRANSCRIPT_CHARS, '... (transkrip dipotong)');

        $actionItemsText = $meeting->actionItems->isEmpty()
            ? 'Tidak ada.'
            : $meeting->actionItems->map(fn ($item) => sprintf(
                '- %s (PIC: %s, Deadline: %s)',
                $item->title,
                $item->assignee_name ?: 'belum ditentukan',
                $item->deadline?->toDateString() ?: 'belum ditentukan',
            ))->implode("\n");

        $tasksText = $meeting->tasks->isEmpty()
            ? 'Tidak ada.'
            : $meeting->tasks->map(fn ($task) => sprintf('- %s (Status: %s)', $task->title, $task->status))->implode("\n");

        $historyText = $meeting->chatMessages()
            ->latest('id')
            ->take(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->map(fn ($m) => ($m->role === 'user' ? 'Pengguna' : 'Asisten').': '.$m->content)
            ->implode("\n");

        return <<<PROMPT
        Kamu adalah asisten AI yang menjawab pertanyaan seputar SATU rapat spesifik berdasarkan
        data di bawah ini SAJA. Jika jawabannya tidak ada di data, katakan dengan jujur bahwa
        informasi tersebut tidak tercatat dalam rapat ini — jangan mengarang jawaban.
        Jawab singkat, jelas, dan dalam Bahasa Indonesia.

        Judul Rapat: {$meeting->title}
        Tanggal: {$meeting->date}
        Rangkuman: {$meeting->summary}

        Transkrip:
        {$transcript}

        Action Items:
        {$actionItemsText}

        Task Terkait:
        {$tasksText}

        Riwayat percakapan sebelumnya:
        {$historyText}

        Pertanyaan baru dari pengguna: {$question}
        PROMPT;
    }
}
