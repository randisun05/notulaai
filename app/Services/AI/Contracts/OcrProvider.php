<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTO\AiTextResult;

interface OcrProvider
{
    /**
     * Ekstrak teks yang terbaca dari sebuah gambar (mis. foto papan tulis/catatan rapat).
     */
    public function extractText(string $absoluteFilePath, string $fileName): AiTextResult;
}
