<?php

namespace App\Services\AI\DTO;

class AiTextResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?float $durationMs = null,
    ) {
    }
}
