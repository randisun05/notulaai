<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use App\Services\AI\Gemini\GeminiKeyPool;
use Gemini\Contracts\ClientContract;

class GeminiTextProvider implements TextGenerationProvider
{
    public function __construct(
        private readonly string $model,
        private readonly GeminiKeyPool $keys,
    ) {}

    public function generate(string $prompt): AiTextResult
    {
        $startedAt = microtime(true);

        $result = $this->keys->run(
            fn (ClientContract $client) => $client->generativeModel(model: $this->model)->generateContent($prompt)
        );

        $durationMs = (microtime(true) - $startedAt) * 1000;

        return new AiTextResult(
            content: $result->text(),
            provider: 'gemini',
            model: $this->model,
            durationMs: $durationMs,
        );
    }
}
