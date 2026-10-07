<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\OcrProvider;
use App\Services\AI\DTO\AiTextResult;
use App\Services\AI\Gemini\GeminiKeyPool;
use Gemini\Contracts\ClientContract;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;

class GeminiOcrProvider implements OcrProvider
{
    private const MIME_TYPES = [
        'jpg' => MimeType::IMAGE_JPEG,
        'jpeg' => MimeType::IMAGE_JPEG,
        'png' => MimeType::IMAGE_PNG,
        'webp' => MimeType::IMAGE_WEBP,
    ];

    public function __construct(
        private readonly string $model,
        private readonly GeminiKeyPool $keys,
    ) {}

    public function extractText(string $absoluteFilePath, string $fileName): AiTextResult
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeType = self::MIME_TYPES[$extension] ?? null;

        if (! $mimeType) {
            throw new \RuntimeException("Tipe gambar tidak didukung untuk OCR: {$extension}");
        }

        $prompt = 'Ekstrak seluruh teks yang terbaca pada gambar ini apa adanya (verbatim), '
            .'termasuk dari catatan tulisan tangan atau papan tulis. Jangan menambahkan komentar, '
            .'hanya kembalikan teksnya saja.';

        $image = new Blob(
            mimeType: $mimeType,
            data: base64_encode(file_get_contents($absoluteFilePath)),
        );

        $startedAt = microtime(true);

        $result = $this->keys->run(
            fn (ClientContract $client) => $client->generativeModel(model: $this->model)->generateContent([$prompt, $image])
        );

        $durationMs = (microtime(true) - $startedAt) * 1000;

        return new AiTextResult(
            content: $result->text(),
            provider: 'gemini_ocr',
            model: $this->model,
            durationMs: $durationMs,
        );
    }
}
