<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use Closure;

class FallbackTextProvider implements TextGenerationProvider
{
    use RunsProviderChain;

    /**
     * @param  array<int, string>  $chain
     * @param  Closure(string): TextGenerationProvider  $resolve
     */
    public function __construct(
        private readonly array $chain,
        private readonly Closure $resolve,
    ) {}

    public function generate(string $prompt): AiTextResult
    {
        return $this->runChain(
            $this->chain,
            $this->resolve,
            'text',
            fn (TextGenerationProvider $provider) => $provider->generate($prompt),
        );
    }
}
