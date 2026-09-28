<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TranscriptionProvider;
use App\Services\AI\DTO\AiTranscriptionResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class WhisperLocalTranscriptionProvider implements TranscriptionProvider
{
    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null, ?string $context = null): AiTranscriptionResult
    {
        $url = Config::get('services.stt_service.url');

        $startedAt = microtime(true);

        try {
            $response = Http::connectTimeout(5)
                ->timeout(1800)
                ->attach('file', file_get_contents($absoluteFilePath), $fileName)
                ->post($url, array_filter(['language' => $language]));
        } catch (ConnectionException $e) {
            throw new \RuntimeException(
                "Server Whisper (STT) tidak dapat dihubungi di {$url}. Pastikan service 'whisper' berjalan.",
                previous: $e,
            );
        }

        if (! $response->successful()) {
            $detail = $response->json('error') ?: $response->body();

            throw new \RuntimeException('Server Whisper (STT) mengembalikan error: '.$detail);
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
