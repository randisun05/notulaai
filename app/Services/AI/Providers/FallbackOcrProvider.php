<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\OcrProvider;
use App\Services\AI\DTO\AiTextResult;
use Closure;

class FallbackOcrProvider implements OcrProvider
{
    use RunsProviderChain;

    /**
     * @param  array<int, string>  $chain
     * @param  Closure(string): OcrProvider  $resolve
     */
    public function __construct(
        private readonly array $chain,
        private readonly Closure $resolve,
    ) {}

    public function extractText(string $absoluteFilePath, string $fileName): AiTextResult
    {
        return $this->runChain(
            $this->chain,
            $this->resolve,
            'ocr',
            fn (OcrProvider $provider) => $provider->extractText($absoluteFilePath, $fileName),
        );
    }
}
