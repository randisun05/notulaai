<?php

namespace App\Services\AI;

use RuntimeException;
use Throwable;

/**
 * Thrown when every provider in a capability's fallback chain has failed.
 * Only used when the chain actually had more than one provider — a single
 * configured provider rethrows its own exception untouched.
 */
class AiProviderChainException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors  provider name => error message, in the order tried
     */
    public function __construct(
        public readonly string $capability,
        public readonly array $errors,
        ?Throwable $previous = null,
    ) {
        $summary = collect($errors)
            ->map(fn (string $message, string $provider) => "{$provider}: {$message}")
            ->implode('; ');

        parent::__construct("Semua provider {$capability} gagal — {$summary}", previous: $previous);
    }
}
