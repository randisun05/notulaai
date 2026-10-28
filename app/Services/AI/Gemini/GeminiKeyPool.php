<?php

namespace App\Services\AI\Gemini;

use Closure;
use Gemini;
use Gemini\Contracts\ClientContract;
use Gemini\Exceptions\ErrorException;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Up to five Gemini API keys (GEMINI_API_KEY, GEMINI_API_KEY_2..5) used as one
 * pool: the first key that is not cooling down serves the request. When Gemini
 * rejects a key because its quota/rate limit is spent (429) or the key itself
 * is bad (400 "API key not valid", 401, 403), that key is benched for a while
 * and the same request is retried right away with the next key.
 *
 * Any other failure (timeout, 5xx, blocked prompt) is not the key's fault, so
 * it is rethrown untouched for the provider-level fallback chain to handle.
 */
class GeminiKeyPool
{
    /**
     * @param  array<int, string>  $keys
     * @param  Closure(string): ClientContract  $clientFactory
     */
    public function __construct(
        private readonly array $keys,
        private readonly Closure $clientFactory,
        private readonly int $cooldownSeconds = 60,
        private readonly int $invalidKeyCooldownSeconds = 3600,
    ) {}

    public static function fromConfig(): self
    {
        $timeout = (int) Config::get('gemini.request_timeout', 60);
        $baseUrl = Config::get('gemini.base_url');

        return new self(
            keys: array_values((array) Config::get('gemini.api_keys', [])),
            clientFactory: function (string $apiKey) use ($timeout, $baseUrl): ClientContract {
                $factory = Gemini::factory()
                    ->withApiKey($apiKey)
                    ->withHttpClient(new GuzzleClient(['timeout' => $timeout]));

                if (! empty($baseUrl)) {
                    $factory->withBaseUrl($baseUrl);
                }

                return $factory->make();
            },
            cooldownSeconds: (int) Config::get('gemini.key_cooldown', 60),
            invalidKeyCooldownSeconds: (int) Config::get('gemini.invalid_key_cooldown', 3600),
        );
    }

    public function count(): int
    {
        return count($this->keys);
    }

    /**
     * @template T
     *
     * @param  Closure(ClientContract): T  $call
     * @return T
     */
    public function run(Closure $call): mixed
    {
        if ($this->keys === []) {
            throw new RuntimeException('GEMINI_API_KEY tidak ditemukan. Cek .env.');
        }

        $errors = [];

        foreach ($this->attemptOrder() as $index) {
            $key = $this->keys[$index];

            try {
                return $call(($this->clientFactory)($key));
            } catch (ErrorException $e) {
                if (! $this->isKeyProblem($e)) {
                    throw $e;
                }

                $label = $this->label($index);
                $errors[] = "{$label}: {$e->getMessage()}";
                $this->benchKey($key, $e);

                Log::warning("Gemini {$label} ditolak ({$e->getErrorCode()}), mencoba key berikutnya: {$e->getMessage()}");
            }
        }

        // A single key has nothing to rotate to — surface Gemini's own error.
        if (count($this->keys) === 1) {
            throw new RuntimeException($errors[0]);
        }

        throw new RuntimeException('Semua API key Gemini gagal (kuota habis / tidak valid). '.implode(' | ', $errors));
    }

    /**
     * Keys that are not cooling down come first, in configured order. Benched
     * keys are still tried last rather than failing outright, since a quota may
     * have reset before the cooldown expires.
     *
     * @return array<int, int>
     */
    private function attemptOrder(): array
    {
        $ready = [];
        $benched = [];

        foreach ($this->keys as $index => $key) {
            if (Cache::has($this->cacheKey($key))) {
                $benched[] = $index;
            } else {
                $ready[] = $index;
            }
        }

        return array_merge($ready, $benched);
    }

    private function isKeyProblem(ErrorException $e): bool
    {
        $code = $e->getErrorCode();

        if (in_array($code, [401, 403, 429], true)) {
            return true;
        }

        return $code === 400 && stripos($e->getMessage(), 'api key') !== false;
    }

    private function benchKey(string $key, ErrorException $e): void
    {
        $seconds = $e->getErrorCode() === 429 ? $this->cooldownSeconds : $this->invalidKeyCooldownSeconds;

        if ($seconds > 0) {
            Cache::put($this->cacheKey($key), true, $seconds);
        }
    }

    /** The raw key never reaches the cache store or the logs. */
    private function cacheKey(string $key): string
    {
        return 'gemini_key_cooldown:'.hash('sha256', $key);
    }

    private function label(int $index): string
    {
        return 'key #'.($index + 1);
    }
}
