<?php

namespace Tests\Unit\Services;

use App\Services\AI\AiRequestLogger;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AiCostAndModelConfigTest extends TestCase
{
    private function logger(): AiRequestLogger
    {
        return $this->app->make(AiRequestLogger::class);
    }

    public function test_cost_is_computed_from_per_million_token_pricing(): void
    {
        Config::set('ai.providers.gemini.pricing', ['input' => 0.30, 'output' => 2.50]);

        // 10k * 0.30/1M + 2k * 2.50/1M = 0.003 + 0.005
        $this->assertSame(0.008, $this->logger()->cost('gemini', 10_000, 2_000));
    }

    public function test_unknown_price_leaves_cost_null(): void
    {
        Config::set('ai.providers.gemini.pricing', ['input' => null, 'output' => null]);

        $this->assertNull($this->logger()->cost('gemini', 10_000, 2_000));
        $this->assertNull($this->logger()->cost('not_a_provider', 10, 10));
    }

    public function test_free_provider_costs_zero_even_without_token_counts(): void
    {
        Config::set('ai.providers.whisper_local.pricing', ['input' => 0.0, 'output' => 0.0]);

        $this->assertSame(0.0, $this->logger()->cost('whisper_local', null, null));
    }

    public function test_priced_provider_without_token_counts_is_null_not_zero(): void
    {
        Config::set('ai.providers.gemini.pricing', ['input' => 0.30, 'output' => 2.50]);

        $this->assertNull($this->logger()->cost('gemini', null, null));
    }

    /** @param array<string, string> $env */
    private function loadAiConfig(array $env): array
    {
        foreach ($env as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        try {
            return require config_path('ai.php');
        } finally {
            foreach (array_keys($env) as $name) {
                unset($_ENV[$name], $_SERVER[$name]);
            }
        }
    }

    public function test_retired_gemini_model_ids_are_replaced_with_the_default(): void
    {
        $config = $this->loadAiConfig(['AI_GEMINI_MODEL' => 'gemini-2.0-flash', 'AI_OCR_MODEL' => 'gemini-2.0-flash-lite']);

        $this->assertSame('gemini-flash-latest', $config['providers']['gemini']['model']);
        $this->assertSame('gemini-flash-latest', $config['providers']['gemini_ocr']['model']);
    }

    public function test_a_current_gemini_model_id_is_kept(): void
    {
        $config = $this->loadAiConfig(['AI_GEMINI_MODEL' => 'gemini-2.5-flash']);

        $this->assertSame('gemini-2.5-flash', $config['providers']['gemini']['model']);
    }

    public function test_free_openrouter_model_is_priced_at_zero_and_paid_one_is_unknown(): void
    {
        $free = $this->loadAiConfig(['AI_OPENROUTER_MODEL' => 'openai/gpt-oss-20b:free']);
        $paid = $this->loadAiConfig(['AI_OPENROUTER_MODEL' => 'openai/gpt-4o-mini']);

        $this->assertSame(['input' => 0.0, 'output' => 0.0], $free['providers']['openrouter']['pricing']);
        $this->assertSame(['input' => null, 'output' => null], $paid['providers']['openrouter']['pricing']);
    }

    public function test_gemini_price_comes_from_env(): void
    {
        $config = $this->loadAiConfig(['AI_GEMINI_PRICE_INPUT' => '0.3', 'AI_GEMINI_PRICE_OUTPUT' => '2.5']);

        $this->assertSame(['input' => 0.3, 'output' => 2.5], $config['providers']['gemini']['pricing']);
    }
}
