<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use Illuminate\Support\Facades\Config;
use OpenAI;

class OpenRouterTextProvider implements TextGenerationProvider
{
    public function __construct(private readonly string $model)
    {
    }

    public function generate(string $prompt): AiTextResult
    {
        $apiKey = Config::get('openai.api_key');
        $baseUri = Config::get('openai.base_uri');

        if (empty($apiKey) || empty($baseUri)) {
            throw new \RuntimeException('OPENAI_API_KEY atau OPENAI_BASE_URI tidak ditemukan di config. Cek .env dan config/openai.php.');
        }

        $client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri($baseUri)
            ->withHttpHeader('HTTP-Referer', 'https://ai-notula-app.test')
            ->withHttpHeader('X-Title', 'AI Notula App')
            ->make();

        $startedAt = microtime(true);

        $response = $client->chat()->create([
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $durationMs = (microtime(true) - $startedAt) * 1000;

        return new AiTextResult(
            content: $response->choices[0]->message->content,
            provider: 'openrouter',
            model: $this->model,
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
            durationMs: $durationMs,
        );
    }
}
