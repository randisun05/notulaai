<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\OcrProvider;
use App\Services\AI\DTO\AiTextResult;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Config;

class GeminiOcrProvider implements OcrProvider
{
    private const MIME_TYPES = [
        'jpg' => MimeType::IMAGE_JPEG,
        'jpeg' => MimeType::IMAGE_JPEG,
        'png' => MimeType::IMAGE_PNG,
        'webp' => MimeType::IMAGE_WEBP,
    ];

    public function __construct(private readonly string $model)
    {
    }

    public function extractText(string $absoluteFilePath, string $fileName): AiTextResult
    {
        $apiKey = Config::get('services.gemini.key');

        if (empty($apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY tidak ditemukan. Cek .env.');
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeType = self::MIME_TYPES[$extension] ?? null;

        if (!$mimeType) {
            throw new \RuntimeException("Tipe gambar tidak didukung untuk OCR: {$extension}");
        }

        $prompt = 'Ekstrak seluruh teks yang terbaca pada gambar ini apa adanya (verbatim), '
            . 'termasuk dari catatan tulisan tangan atau papan tulis. Jangan menambahkan komentar, '
            . 'hanya kembalikan teksnya saja.';

        $startedAt = microtime(true);

        $result = Gemini::client($apiKey)
            ->generativeModel(model: $this->model)
            ->generateContent([
                $prompt,
                new Blob(
                    mimeType: $mimeType,
                    data: base64_encode(file_get_contents($absoluteFilePath)),
                ),
            ]);

        $durationMs = (microtime(true) - $startedAt) * 1000;

        return new AiTextResult(
            content: $result->text(),
            provider: 'gemini_ocr',
            model: $this->model,
            durationMs: $durationMs,
        );
    }
}
