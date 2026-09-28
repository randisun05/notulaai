<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TranscriptionProvider;
use App\Services\AI\DTO\AiTranscriptionResult;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Transkripsi lewat Gemini (audio dikirim inline). Dirancang untuk potongan
 * ±10 menit dari AudioSplitter — bukan rekaman utuh berjam-jam.
 */
class GeminiTranscriptionProvider implements TranscriptionProvider
{
    /** Batas request inline Gemini 20 MB (setelah base64 ±33% lebih besar). */
    private const MAX_INLINE_BYTES = 14 * 1024 * 1024;

    private const MIME_TYPES = [
        'mp3' => MimeType::AUDIO_MP3,
        'wav' => MimeType::AUDIO_WAV,
        'aac' => MimeType::AUDIO_AAC,
        'm4a' => MimeType::AUDIO_AAC,
        'ogg' => MimeType::AUDIO_OGG,
        'flac' => MimeType::AUDIO_FLAC,
    ];

    public function __construct(private readonly string $model) {}

    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null, ?string $context = null): AiTranscriptionResult
    {
        if (empty(Config::get('gemini.api_key'))) {
            throw new RuntimeException('GEMINI_API_KEY tidak ditemukan. Cek .env.');
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeType = self::MIME_TYPES[$extension] ?? throw new RuntimeException("Format audio tidak didukung untuk transkripsi Gemini: {$extension}");

        if (filesize($absoluteFilePath) > self::MAX_INLINE_BYTES) {
            throw new RuntimeException('Potongan audio terlalu besar untuk dikirim ke Gemini (maks. 14 MB).');
        }

        $prompt = 'Transkripsikan rekaman rapat ini kata per kata (verbatim) dalam bahasa aslinya'
            .($language ? " (terutama {$language})" : '').'. '
            .'Awali setiap giliran bicara dengan baris baru berformat "Nama: ..." jika nama pembicara '
            .'disebut dalam percakapan, atau "Pembicara 1: ...", "Pembicara 2: ..." jika tidak. '
            .'Jangan meringkas, jangan menambahkan komentar atau timestamp. '
            .'Jika tidak ada ucapan yang terdengar, balas dengan teks kosong.'
            .($context ? "\n\nKonteks (bukan bagian dari audio, jangan ditranskrip ulang):\n{$context}\n"
                .'Rekaman ini adalah lanjutan langsung. Pakai label pembicara yang SAMA untuk orang yang sama seperti di '
                .'konteks, dan pakai nama peserta bila suaranya jelas milik orang yang namanya sudah disebut.' : '');

        $startedAt = microtime(true);

        $result = Gemini::generativeModel(model: $this->model)->generateContent([
            $prompt,
            new Blob(mimeType: $mimeType, data: base64_encode(file_get_contents($absoluteFilePath))),
        ]);

        return new AiTranscriptionResult(
            text: trim($result->text()),
            provider: 'gemini_stt',
            language: $language,
            durationMs: (microtime(true) - $startedAt) * 1000,
        );
    }
}
