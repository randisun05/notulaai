<?php

namespace Tests\Unit\Services;

use App\Services\AI\Gemini\GeminiKeyPool;
use App\Services\AI\Providers\GeminiTextProvider;
use Gemini\Contracts\ClientContract;
use Gemini\Exceptions\ErrorException;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Testing\ClientFake;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class GeminiKeyPoolTest extends TestCase
{
    /** @var array<int, string> keys in the order the factory was asked for them */
    private array $used = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->used = [];
        Cache::flush();
    }

    /**
     * @param  array<string, array<int, mixed>>  $responsesPerKey
     */
    private function pool(array $responsesPerKey): GeminiKeyPool
    {
        $fakes = array_map(fn (array $responses) => new ClientFake($responses), $responsesPerKey);

        return new GeminiKeyPool(
            keys: array_keys($responsesPerKey),
            clientFactory: function (string $key) use ($fakes): ClientContract {
                $this->used[] = $key;

                return $fakes[$key];
            },
            cooldownSeconds: 60,
        );
    }

    private function ok(string $text): GenerateContentResponse
    {
        return GenerateContentResponse::fake([
            'candidates' => [['content' => ['parts' => [['text' => $text]]]]],
        ]);
    }

    private function geminiError(int $code, string $status, string $message): ErrorException
    {
        return new ErrorException(['code' => $code, 'status' => $status, 'message' => $message]);
    }

    private function generate(GeminiKeyPool $pool): string
    {
        return $pool->run(fn (ClientContract $c) => $c->generativeModel(model: 'gemini-test')->generateContent('halo'))->text();
    }

    public function test_uses_the_first_key_when_it_works(): void
    {
        $pool = $this->pool(['key-a' => [$this->ok('dari a')], 'key-b' => [$this->ok('dari b')]]);

        $this->assertSame('dari a', $this->generate($pool));
        $this->assertSame(['key-a'], $this->used);
    }

    public function test_rotates_to_the_next_key_when_the_quota_is_exhausted(): void
    {
        $pool = $this->pool([
            'key-a' => [$this->geminiError(429, 'RESOURCE_EXHAUSTED', 'Quota exceeded')],
            'key-b' => [$this->geminiError(429, 'RESOURCE_EXHAUSTED', 'Quota exceeded')],
            'key-c' => [$this->ok('dari c')],
        ]);

        $this->assertSame('dari c', $this->generate($pool));
        $this->assertSame(['key-a', 'key-b', 'key-c'], $this->used);
    }

    public function test_rotates_past_an_invalid_key(): void
    {
        $pool = $this->pool([
            'key-a' => [$this->geminiError(400, 'INVALID_ARGUMENT', 'API key not valid. Please pass a valid API key.')],
            'key-b' => [$this->ok('dari b')],
        ]);

        $this->assertSame('dari b', $this->generate($pool));
    }

    public function test_an_exhausted_key_is_skipped_on_the_next_request(): void
    {
        $pool = $this->pool([
            'key-a' => [$this->geminiError(429, 'RESOURCE_EXHAUSTED', 'Quota exceeded')],
            'key-b' => [$this->ok('satu'), $this->ok('dua')],
        ]);

        $this->generate($pool);
        $this->used = [];

        $this->assertSame('dua', $this->generate($pool));
        $this->assertSame(['key-b'], $this->used, 'key-a is cooling down and must not be tried first');
    }

    public function test_errors_that_are_not_about_the_key_do_not_rotate(): void
    {
        $pool = $this->pool([
            'key-a' => [$this->geminiError(500, 'INTERNAL', 'Internal error')],
            'key-b' => [$this->ok('dari b')],
        ]);

        try {
            $this->generate($pool);
            $this->fail('expected the 500 to propagate');
        } catch (ErrorException $e) {
            $this->assertSame(500, $e->getErrorCode());
        }

        $this->assertSame(['key-a'], $this->used);
    }

    public function test_when_every_key_is_exhausted_it_reports_each_one_without_leaking_the_key(): void
    {
        $pool = $this->pool([
            'secret-key-a' => [$this->geminiError(429, 'RESOURCE_EXHAUSTED', 'Quota exceeded')],
            'secret-key-b' => [$this->geminiError(403, 'PERMISSION_DENIED', 'Key was reported as leaked')],
        ]);

        try {
            $this->generate($pool);
            $this->fail('expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('key #1', $e->getMessage());
            $this->assertStringContainsString('key #2', $e->getMessage());
            $this->assertStringNotContainsString('secret-key', $e->getMessage());
        }
    }

    public function test_no_configured_key_fails_with_a_clear_message(): void
    {
        $pool = new GeminiKeyPool([], fn () => new ClientFake);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GEMINI_API_KEY');

        $this->generate($pool);
    }

    public function test_config_collects_up_to_five_keys_skipping_blanks_and_duplicates(): void
    {
        $env = [
            'GEMINI_API_KEY' => 'key-a',
            'GEMINI_API_KEY_2' => '',
            'GEMINI_API_KEY_3' => ' key-b ',
            'GEMINI_API_KEY_4' => 'key-a',
            'GEMINI_API_KEY_5' => 'key-c',
        ];

        foreach ($env as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        try {
            $config = require config_path('gemini.php');
        } finally {
            foreach (array_keys($env) as $name) {
                unset($_ENV[$name], $_SERVER[$name]);
            }
        }

        $this->assertSame(['key-a', 'key-b', 'key-c'], $config['api_keys']);
    }

    public function test_text_provider_goes_through_the_pool(): void
    {
        $this->app->instance(GeminiKeyPool::class, $this->pool([
            'key-a' => [$this->geminiError(429, 'RESOURCE_EXHAUSTED', 'Quota exceeded')],
            'key-b' => [$this->ok('ringkasan')],
        ]));

        $provider = $this->app->makeWith(GeminiTextProvider::class, ['model' => 'gemini-test']);
        $result = $provider->generate('halo');

        $this->assertSame('ringkasan', $result->content);
        $this->assertSame('gemini', $result->provider);
    }
}
