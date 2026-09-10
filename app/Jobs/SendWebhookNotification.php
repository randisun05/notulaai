<?php

namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebhookNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 60, 300];

    public function __construct(
        private readonly Webhook $webhook,
        private readonly string $event,
        private readonly array $data,
    ) {}

    public function handle(): void
    {
        $body = json_encode([
            'event' => $this->event,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ]);

        $signature = hash_hmac('sha256', $body, $this->webhook->secret);

        Http::withBody($body, 'application/json')
            ->withHeaders([
                'X-Notula-Event' => $this->event,
                'X-Notula-Signature' => $signature,
            ])
            ->timeout(10)
            ->throw()
            ->post($this->webhook->url);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning("Pengiriman webhook #{$this->webhook->id} ({$this->event}) gagal setelah semua percobaan: {$exception->getMessage()}");
    }
}
