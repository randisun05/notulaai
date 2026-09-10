<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Config;
use OpenAI;
use Psr\Http\Message\ResponseInterface;

class OpenRouterTextProvider implements TextGenerationProvider
{
    public function __construct(private readonly string $model) {}

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
            ->withHttpClient($this->makeHttpClient())
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

    /**
     * OpenRouter kadang mengirim `usage.completion_tokens_details` tanpa field
     * accepted_prediction_tokens/rejected_prediction_tokens, padahal openai-php/client
     * v0.10.x mewajibkan keduanya (int, bukan nullable) saat hydrate DTO respons —
     * hasilnya TypeError setiap kali field itu hilang. Tambal di level HTTP response
     * sebelum diparse oleh client, daripada menunggu upstream package memperbaikinya
     * (versi yang lebih baru butuh Laravel 11+, sementara app ini masih Laravel 10).
     */
    private function makeHttpClient(): GuzzleClient
    {
        $stack = HandlerStack::create();

        $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (! is_array($data) || ! isset($data['usage']['completion_tokens_details']) || ! is_array($data['usage']['completion_tokens_details'])) {
                return $response;
            }

            $data['usage']['completion_tokens_details'] += [
                'reasoning_tokens' => 0,
                'accepted_prediction_tokens' => 0,
                'rejected_prediction_tokens' => 0,
            ];

            return $response->withBody(Utils::streamFor(json_encode($data)));
        }));

        return new GuzzleClient([
            'handler' => $stack,
            'connect_timeout' => 10,
            'timeout' => (int) Config::get('ai.http_timeout', 60),
        ]);
    }
}
