<?php

namespace App\Services\AI;

use App\Models\Setting;
use App\Services\AI\Contracts\OcrProvider;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\Contracts\TranscriptionProvider;
use App\Services\AI\Providers\FallbackOcrProvider;
use App\Services\AI\Providers\FallbackTextProvider;
use App\Services\AI\Providers\FallbackTranscriptionProvider;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;

class AiManager
{
    public function __construct(private readonly Container $container) {}

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

    public function activeOcrProvider(): string
    {
        return Setting::current()->ai_ocr_provider ?: Config::get('ai.default_ocr_provider');
    }

    /**
     * Nama provider yang benar-benar dicoba untuk sebuah kapabilitas: provider
     * aktif dulu, lalu tiap fallback yang dikonfigurasi (tanpa duplikat).
     *
     * @return array<int, string>
     */
    public function textChain(): array
    {
        return $this->buildChain($this->activeTextProvider(), 'text');
    }

    /** @return array<int, string> */
    public function transcriptionChain(): array
    {
        return $this->buildChain($this->activeTranscriptionProvider(), 'transcription');
    }

    /** @return array<int, string> */
    public function ocrChain(): array
    {
        return $this->buildChain($this->activeOcrProvider(), 'ocr');
    }

    /**
     * Tanpa argumen: mengembalikan pembungkus yang menelusuri rantai fallback.
     * Dengan nama provider: provider tunggal itu saja, tanpa fallback.
     */
    public function text(?string $provider = null): TextGenerationProvider
    {
        if ($provider !== null) {
            return $this->resolve($provider, TextGenerationProvider::class);
        }

        return new FallbackTextProvider($this->textChain(), $this->resolver(TextGenerationProvider::class));
    }

    public function transcription(?string $provider = null): TranscriptionProvider
    {
        if ($provider !== null) {
            return $this->resolve($provider, TranscriptionProvider::class);
        }

        return new FallbackTranscriptionProvider($this->transcriptionChain(), $this->resolver(TranscriptionProvider::class));
    }

    public function ocr(?string $provider = null): OcrProvider
    {
        if ($provider !== null) {
            return $this->resolve($provider, OcrProvider::class);
        }

        return new FallbackOcrProvider($this->ocrChain(), $this->resolver(OcrProvider::class));
    }

    /**
     * @return array<int, string>
     */
    private function buildChain(string $active, string $capability): array
    {
        $fallbacks = (array) Config::get("ai.fallbacks.{$capability}", []);

        return array_values(array_unique(array_merge([$active], $fallbacks)));
    }

    private function resolver(string $expectedInterface): Closure
    {
        return fn (string $provider) => $this->resolve($provider, $expectedInterface);
    }

    private function resolve(string $provider, string $expectedInterface): object
    {
        $config = Config::get("ai.providers.{$provider}");

        if (! $config || ! isset($config['driver'])) {
            throw new \InvalidArgumentException("AI provider [{$provider}] tidak dikonfigurasi di config/ai.php.");
        }

        $instance = $this->container->makeWith($config['driver'], [
            'model' => $config['model'] ?? null,
        ]);

        if (! $instance instanceof $expectedInterface) {
            throw new \InvalidArgumentException("Driver untuk provider [{$provider}] harus mengimplementasikan {$expectedInterface}.");
        }

        return $instance;
    }
}
