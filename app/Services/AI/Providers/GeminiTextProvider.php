<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Config;

class GeminiTextProvider implements TextGenerationProvider
{
    public function __construct(private readonly string $model)
    {
    }

    public function generate(string $prompt): AiTextResult
    {
        $apiKey = Config::get('services.gemini.key');

        if (empty($apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY tidak ditemukan. Cek .env.');
        }

        $startedAt = microtime(true);

        $result = Gemini::client($apiKey)
            ->generativeModel(model: $this->model)
            ->generateContent($prompt);

        $durationMs = (microtime(true) - $startedAt) * 1000;

        return new AiTextResult(
            content: $result->text(),
            provider: 'gemini',
            model: $this->model,
            durationMs: $durationMs,
        );
    }
}
