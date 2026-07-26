<?php

namespace App\Services\Meeting;

use App\Mail\MeetingSummary;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AI\AiManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MeetingProcessingService
{
    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'm4a'];
    private const TEXT_EXTENSIONS = ['txt', 'md'];

    public function __construct(private readonly AiManager $ai)
    {
    }

    public function process(Meeting $meeting): void
    {
        Log::info("Memulai pemrosesan untuk Rapat ID: {$meeting->id}");

        $transcript = $this->extractTranscript($meeting);

        Log::info("Transkrip berhasil dibuat untuk Rapat ID: {$meeting->id}");

        $summary = $this->summarize($transcript);

        Log::info("Rangkuman berhasil dibuat untuk Rapat ID: {$meeting->id}");

        $meeting->update([
            'transcript' => $transcript,
            'summary' => $summary,
            'status' => 'Selesai Diproses',
        ]);
        $meeting->refresh();

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
            $result = $this->ai->transcription()->transcribe(
                absoluteFilePath: Storage::disk('public')->path($filePath),
                fileName: basename($filePath),
                language: 'Indonesian',
            );

            return $result->text;
        }

        if (in_array($extension, self::TEXT_EXTENSIONS)) {
            $transcript = Storage::disk('public')->get($filePath);

            if (empty(trim($transcript))) {
                throw new \RuntimeException('Transkrip kosong, tidak bisa membuat rangkuman.');
            }

            return $transcript;
        }

        throw new \RuntimeException("Tipe file tidak didukung: {$extension}");
    }

    private function summarize(string $transcript): string
    {
        $prompt = 'You are a helpful assistant that summarizes meeting transcripts. Create a summary in well-structured HTML format. '
            . 'Use headings (<h3>), unordered lists (<ul><li>) for key points, and bold tags (<b>) to highlight action items or names. '
            . 'Here is the transcript: ' . $transcript;

        return $this->ai->text()->generate($prompt)->content;
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
