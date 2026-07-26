<?php

namespace App\Services\AI;

use App\Models\AiRequestLog;
use App\Models\Meeting;
use App\Models\User;

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
            'status' => 'success',
        ]);
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
