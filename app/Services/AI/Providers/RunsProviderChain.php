<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderChainException;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared logic for the Fallback* provider wrappers: walk an ordered list of
 * provider names, return the first success, and only raise the aggregate
 * AiProviderChainException when more than one provider was actually tried.
 */
trait RunsProviderChain
{
    /**
     * @param  array<int, string>  $chain  ordered provider names
     * @param  Closure(string): object  $resolve  provider name => driver instance
     * @param  Closure(object): mixed  $call  driver instance => result DTO
     */
    private function runChain(array $chain, Closure $resolve, string $capability, Closure $call): mixed
    {
        $errors = [];
        $lastException = null;

        foreach ($chain as $index => $name) {
            try {
                return $call($resolve($name));
            } catch (Throwable $e) {
                $lastException = $e;
                $errors[$name] = $e->getMessage();

                if (count($chain) > 1) {
                    Log::warning(sprintf(
                        'AI %s: provider [%s] gagal (%d/%d): %s',
                        $capability, $name, $index + 1, count($chain), $e->getMessage()
                    ));
                }
            }
        }

        // A single-provider chain has no fallback semantics — let the real
        // exception propagate so callers/logs see the original message.
        if (count($chain) === 1) {
            throw $lastException;
        }

        throw new AiProviderChainException($capability, $errors, $lastException);
    }
}
