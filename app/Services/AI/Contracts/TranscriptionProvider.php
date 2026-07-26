<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTO\AiTranscriptionResult;

interface TranscriptionProvider
{
    /**
     * Transkripsikan file audio menjadi teks.
     */
    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null): AiTranscriptionResult;
}
