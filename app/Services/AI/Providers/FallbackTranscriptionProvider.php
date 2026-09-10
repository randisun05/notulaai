<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TranscriptionProvider;
use App\Services\AI\DTO\AiTranscriptionResult;
use Closure;

class FallbackTranscriptionProvider implements TranscriptionProvider
{
    use RunsProviderChain;

    /**
     * @param  array<int, string>  $chain
     * @param  Closure(string): TranscriptionProvider  $resolve
     */
    public function __construct(
        private readonly array $chain,
        private readonly Closure $resolve,
    ) {}

    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null): AiTranscriptionResult
    {
        return $this->runChain(
            $this->chain,
            $this->resolve,
            'transcription',
            fn (TranscriptionProvider $provider) => $provider->transcribe($absoluteFilePath, $fileName, $language),
        );
    }
}
