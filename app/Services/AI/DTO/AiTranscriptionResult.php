<?php

namespace App\Services\AI\DTO;

class AiTranscriptionResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly ?string $language = null,
        public readonly ?float $durationMs = null,
    ) {}
}
