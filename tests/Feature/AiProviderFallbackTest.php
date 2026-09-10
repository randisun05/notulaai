<?php

namespace Tests\Feature;

use App\Services\AI\AiManager;
use App\Services\AI\AiProviderChainException;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class AiProviderFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FallbackTestBoomProvider::$calls = 0;
        FallbackTestOkProvider::$calls = 0;

        Config::set('ai.providers.boom', ['driver' => FallbackTestBoomProvider::class, 'model' => 'boom-model']);
        Config::set('ai.providers.boom_two', ['driver' => FallbackTestBoomTwoProvider::class, 'model' => 'boom-two-model']);
        Config::set('ai.providers.ok', ['driver' => FallbackTestOkProvider::class, 'model' => 'ok-model']);
        Config::set('ai.default_text_provider', 'boom');
    }

    private function manager(): AiManager
    {
        return $this->app->make(AiManager::class);
    }

    public function test_falls_back_to_the_next_provider_when_the_active_one_fails(): void
    {
        Config::set('ai.fallbacks.text', ['ok']);

        $result = $this->manager()->text()->generate('halo');

        $this->assertSame('ok says: halo', $result->content);
        $this->assertSame('ok', $result->provider);
        $this->assertSame(1, FallbackTestBoomProvider::$calls, 'active provider should have been tried first');
        $this->assertSame(1, FallbackTestOkProvider::$calls);
    }

    public function test_active_provider_success_never_touches_the_fallback(): void
    {
        Config::set('ai.default_text_provider', 'ok');
        Config::set('ai.fallbacks.text', ['boom']);

        $result = $this->manager()->text()->generate('halo');

        $this->assertSame('ok', $result->provider);
        $this->assertSame(0, FallbackTestBoomProvider::$calls);
    }

    public function test_when_every_provider_in_a_multi_chain_fails_it_raises_an_aggregate_error(): void
    {
        Config::set('ai.fallbacks.text', ['boom_two']);

        try {
            $this->manager()->text()->generate('halo');
            $this->fail('expected AiProviderChainException');
        } catch (AiProviderChainException $e) {
            $this->assertSame('text', $e->capability);
            $this->assertArrayHasKey('boom', $e->errors);
            $this->assertArrayHasKey('boom_two', $e->errors);
            $this->assertStringContainsString('boom', $e->getMessage());
            $this->assertStringContainsString('boom_two', $e->getMessage());
        }
    }

    public function test_a_single_provider_chain_rethrows_the_original_exception_untouched(): void
    {
        Config::set('ai.fallbacks.text', []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom provider exploded');

        $this->manager()->text()->generate('halo');
    }

    public function test_explicit_provider_name_bypasses_the_fallback_chain(): void
    {
        Config::set('ai.fallbacks.text', ['ok']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom provider exploded');

        $this->manager()->text('boom')->generate('halo');
    }
}

class FallbackTestBoomProvider implements TextGenerationProvider
{
    public static int $calls = 0;

    public function __construct(private readonly string $model) {}

    public function generate(string $prompt): AiTextResult
    {
        self::$calls++;

        throw new RuntimeException('boom provider exploded');
    }
}

class FallbackTestBoomTwoProvider implements TextGenerationProvider
{
    public function __construct(private readonly string $model) {}

    public function generate(string $prompt): AiTextResult
    {
        throw new RuntimeException('boom two also exploded');
    }
}

class FallbackTestOkProvider implements TextGenerationProvider
{
    public static int $calls = 0;

    public function __construct(private readonly string $model) {}

    public function generate(string $prompt): AiTextResult
    {
        self::$calls++;

        return new AiTextResult(
            content: 'ok says: '.$prompt,
            provider: 'ok',
            model: $this->model,
        );
    }
}
