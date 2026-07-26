<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TranscriptionProvider;
use App\Services\AI\DTO\AiTranscriptionResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class WhisperLocalTranscriptionProvider implements TranscriptionProvider
{
    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null): AiTranscriptionResult
    {
        $url = Config::get('services.stt_service.url');

        $startedAt = microtime(true);

        $response = Http::timeout(300)
            ->attach('file', file_get_contents($absoluteFilePath), $fileName)
            ->post($url, array_filter(['language' => $language]));

        if (!$response->successful()) {
            throw new \RuntimeException('Server Whisper (STT) gagal: ' . $response->body());
        }

        $text = $response->json('text');
        if (empty($text)) {
            throw new \RuntimeException('Transkrip dari server Whisper kosong.');
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        return new AiTranscriptionResult(
            text: $text,
            provider: 'whisper_local',
            language: $response->json('language'),
            durationMs: $durationMs,
        );
    }
}
