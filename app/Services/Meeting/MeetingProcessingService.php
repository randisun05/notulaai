<?php

namespace App\Services\Meeting;

use App\Mail\MeetingSummary;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MeetingProcessingService
{
    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'm4a'];
    private const TEXT_EXTENSIONS = ['txt', 'md'];
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly AiManager $ai,
        private readonly AiRequestLogger $logger,
        private readonly ActionItemsParser $actionItemsParser,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function process(Meeting $meeting): void
    {
        Log::info("Memulai pemrosesan untuk Rapat ID: {$meeting->id}");

        $transcript = $this->extractTranscript($meeting);

        Log::info("Transkrip berhasil dibuat untuk Rapat ID: {$meeting->id}");

        $summary = $this->summarize($meeting, $transcript);

        Log::info("Rangkuman berhasil dibuat untuk Rapat ID: {$meeting->id}");

        $meeting->update([
            'transcript' => $transcript,
            'summary' => $summary,
            'status' => 'Selesai Diproses',
        ]);
        $meeting->refresh();

        // Action items bersifat pelengkap: kalau AI gagal menghasilkan/mem-parse-nya,
        // notula tetap dianggap berhasil diproses (transkrip + rangkuman sudah aman).
        $this->generateActionItems($meeting, $transcript);

        $this->activityLogger->log($meeting, null, 'meeting.processed', 'AI berhasil membuat transkrip dan rangkuman notula.');

        $this->notifyUnit($meeting);
    }

    private function extractTranscript(Meeting $meeting): string
    {
        $filePath = $meeting->source_file_path;
        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            throw new \RuntimeException("File sumber tidak ditemukan di path: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, self::AUDIO_EXTENSIONS)) {
            $provider = $this->ai->activeTranscriptionProvider();
            $fileName = basename($filePath);

            try {
                $result = $this->ai->transcription()->transcribe(
                    absoluteFilePath: Storage::disk('public')->path($filePath),
                    fileName: $fileName,
                    language: 'Indonesian',
                );
            } catch (Throwable $e) {
                $this->logger->logFailure('transcription', $provider, null, $fileName, $e->getMessage(), $meeting, $meeting->creator);
                throw $e;
            }

            $this->logger->logSuccess('transcription', $provider, null, $fileName, $result->text, null, null, $result->durationMs, $meeting, $meeting->creator);

            return $result->text;
        }

        if (in_array($extension, self::TEXT_EXTENSIONS)) {
            $transcript = Storage::disk('public')->get($filePath);

            if (empty(trim($transcript))) {
                throw new \RuntimeException('Transkrip kosong, tidak bisa membuat rangkuman.');
            }

            return $transcript;
        }

        if (in_array($extension, self::IMAGE_EXTENSIONS)) {
            $provider = $this->ai->activeOcrProvider();
            $fileName = basename($filePath);

            try {
                $result = $this->ai->ocr()->extractText(
                    absoluteFilePath: Storage::disk('public')->path($filePath),
                    fileName: $fileName,
                );
            } catch (Throwable $e) {
                $this->logger->logFailure('ocr', $provider, null, $fileName, $e->getMessage(), $meeting, $meeting->creator);
                throw $e;
            }

            if (empty(trim($result->content))) {
                throw new \RuntimeException('Tidak ada teks yang terbaca dari gambar tersebut.');
            }

            $this->logger->logSuccess('ocr', $provider, $result->model, $fileName, $result->content, null, null, $result->durationMs, $meeting, $meeting->creator);

            return $result->content;
        }

        throw new \RuntimeException("Tipe file tidak didukung: {$extension}");
    }

    private function summarize(Meeting $meeting, string $transcript): string
    {
        $prompt = 'You are a helpful assistant that summarizes meeting transcripts. Create a summary in well-structured HTML format. '
            . 'Use headings (<h3>), unordered lists (<ul><li>) for key points, and bold tags (<b>) to highlight action items or names. '
            . 'Here is the transcript: ' . $transcript;

        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('text', $provider, $model, $prompt, $e->getMessage(), $meeting, $meeting->creator);
            throw $e;
        }

        $this->logger->logSuccess('text', $provider, $model, $prompt, $result->content, $result->promptTokens, $result->completionTokens, $result->durationMs, $meeting, $meeting->creator);

        return $result->content;
    }

    private function generateActionItems(Meeting $meeting, string $transcript): void
    {
        $prompt = <<<PROMPT
        Dari transkrip rapat berikut, ekstrak daftar action item (tindak lanjut) yang disebutkan.
        Balas HANYA dengan JSON array yang valid, tanpa teks lain dan tanpa markdown code fence.
        Setiap elemen array berbentuk objek dengan field:
        - "title": deskripsi singkat tindakan yang harus dilakukan (wajib diisi)
        - "assignee_name": nama orang/tim penanggung jawab jika disebutkan, atau null jika tidak ada
        - "deadline": tanggal tenggat dalam format YYYY-MM-DD jika disebutkan, atau null jika tidak ada

        Jika tidak ada action item yang jelas, balas dengan array kosong: []

        Transkrip:
        {$transcript}
        PROMPT;

        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('action_items', $provider, $model, $prompt, $e->getMessage(), $meeting, $meeting->creator);
            Log::warning("Gagal membuat action items untuk Rapat ID {$meeting->id}: " . $e->getMessage());

            return;
        }

        $this->logger->logSuccess('action_items', $provider, $model, $prompt, $result->content, $result->promptTokens, $result->completionTokens, $result->durationMs, $meeting, $meeting->creator);

        $items = $this->actionItemsParser->parse($result->content);

        $meeting->actionItems()->delete();

        foreach ($items as $index => $item) {
            MeetingActionItem::create([
                'meeting_id' => $meeting->id,
                'title' => $item['title'],
                'assignee_name' => $item['assignee_name'],
                'deadline' => $item['deadline'],
                'order' => $index,
            ]);
        }

        Log::info(count($items) . " action item dibuat untuk Rapat ID: {$meeting->id}");
    }

    private function notifyUnit(Meeting $meeting): void
    {
        if (!$meeting->unit_id) {
            Log::warning("Tidak ada unit_id untuk Rapat ID: {$meeting->id}, email tidak dikirim.");
            return;
        }

        $usersInUnit = User::where('unit_id', $meeting->unit_id)
            ->whereNotNull('email')
            ->get();

        if ($usersInUnit->isEmpty()) {
            Log::warning("Tidak ada user ditemukan di unit ID {$meeting->unit_id}, email tidak dikirim.");
            return;
        }

        Log::info("Mengirim email hasil rapat ke {$usersInUnit->count()} pengguna di unit ID {$meeting->unit_id}.");

        foreach ($usersInUnit as $user) {
            try {
                Mail::to($user->email)->send(new MeetingSummary($meeting));
                Log::info("Email hasil rapat terkirim ke {$user->email}");
            } catch (\Exception $e) {
                Log::error("Gagal mengirim email hasil rapat ke {$user->email}: " . $e->getMessage());
            }
        }
    }
}
