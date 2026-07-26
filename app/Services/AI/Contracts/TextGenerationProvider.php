<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTO\AiTextResult;

interface TextGenerationProvider
{
    /**
     * Hasilkan teks (mis. ringkasan) dari sebuah prompt.
     */
    public function generate(string $prompt): AiTextResult;
}
