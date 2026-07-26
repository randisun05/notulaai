<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\Contracts\TranscriptionProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;

class AiManager
{
    public function __construct(private readonly Container $container)
    {
    }

    public function text(?string $provider = null): TextGenerationProvider
    {
        $provider ??= Config::get('ai.default_text_provider');

        return $this->resolve($provider, TextGenerationProvider::class);
    }

    public function transcription(?string $provider = null): TranscriptionProvider
    {
        $provider ??= Config::get('ai.default_transcription_provider');

        return $this->resolve($provider, TranscriptionProvider::class);
    }

    private function resolve(string $provider, string $expectedInterface): object
    {
        $config = Config::get("ai.providers.{$provider}");

        if (!$config || !isset($config['driver'])) {
            throw new \InvalidArgumentException("AI provider [{$provider}] tidak dikonfigurasi di config/ai.php.");
        }

        $instance = $this->container->makeWith($config['driver'], [
            'model' => $config['model'] ?? null,
        ]);

        if (!$instance instanceof $expectedInterface) {
            throw new \InvalidArgumentException("Driver untuk provider [{$provider}] harus mengimplementasikan {$expectedInterface}.");
        }

        return $instance;
    }
}
