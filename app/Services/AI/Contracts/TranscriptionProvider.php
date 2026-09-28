<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTO\AiTranscriptionResult;

interface TranscriptionProvider
{
    /**
     * Transkripsikan file audio menjadi teks.
     *
     * @param  string|null  $context  petunjuk untuk menjaga kesinambungan antar-potongan
     *                                (daftar peserta, akhir potongan sebelumnya); driver
     *                                yang tidak bisa memakainya boleh mengabaikannya.
     */
    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null, ?string $context = null): AiTranscriptionResult;
}
