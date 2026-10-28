<?php

namespace App\Services\AI;

use App\Models\AiRequestLog;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Config;

class AiRequestLogger
{
    public function logSuccess(
        string $type,
        string $provider,
        ?string $model,
        ?string $prompt,
        string $response,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?float $durationMs = null,
        ?Meeting $meeting = null,
        ?User $user = null,
    ): void {
        AiRequestLog::create([
            'meeting_id' => $meeting?->id,
            'user_id' => $user?->id,
            'type' => $type,
            'provider' => $provider,
            'model' => $model,
            'prompt' => $prompt,
            'response' => $response,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'duration_ms' => $durationMs,
            'cost' => $this->cost($provider, $promptTokens, $completionTokens),
            'status' => 'success',
        ]);
    }

    /**
     * USD cost from config('ai.providers.<provider>.pricing') (per 1M tokens).
     * Null when the price is unknown, or when a priced provider reported no
     * token counts — a guessed 0 would understate real spend.
     */
    public function cost(string $provider, ?int $promptTokens, ?int $completionTokens): ?float
    {
        $pricing = Config::get("ai.providers.{$provider}.pricing");
        $input = $pricing['input'] ?? null;
        $output = $pricing['output'] ?? null;

        if ($input === null || $output === null) {
            return null;
        }

        if ((float) $input === 0.0 && (float) $output === 0.0) {
            return 0.0;
        }

        if ($promptTokens === null && $completionTokens === null) {
            return null;
        }

        return round((($promptTokens ?? 0) * $input + ($completionTokens ?? 0) * $output) / 1_000_000, 6);
    }

    public function logFailure(
        string $type,
        string $provider,
        ?string $model,
        ?string $prompt,
        string $errorMessage,
        ?Meeting $meeting = null,
        ?User $user = null,
    ): void {
        AiRequestLog::create([
            'meeting_id' => $meeting?->id,
            'user_id' => $user?->id,
            'type' => $type,
            'provider' => $provider,
            'model' => $model,
            'prompt' => $prompt,
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
