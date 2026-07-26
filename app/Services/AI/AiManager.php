<?php

namespace App\Services\AI;

use App\Models\Setting;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\Contracts\TranscriptionProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;

class AiManager
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Provider aktif bisa di-override lewat halaman Settings (Admin); jika belum
     * pernah diatur, jatuh kembali ke default di config/ai.php.
     */
    public function activeTextProvider(): string
    {
        return Setting::current()->ai_text_provider ?: Config::get('ai.default_text_provider');
    }

    public function activeTranscriptionProvider(): string
    {
        return Setting::current()->ai_transcription_provider ?: Config::get('ai.default_transcription_provider');
    }

    public function text(?string $provider = null): TextGenerationProvider
    {
        $provider ??= $this->activeTextProvider();

        return $this->resolve($provider, TextGenerationProvider::class);
    }

    public function transcription(?string $provider = null): TranscriptionProvider
    {
        $provider ??= $this->activeTranscriptionProvider();

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
