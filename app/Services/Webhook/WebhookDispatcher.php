<?php

namespace App\Services\Webhook;

use App\Jobs\SendWebhookNotification;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Model;

/**
 * Titik panggil tunggal untuk memicu webhook keluar. Dipanggil langsung dari
 * controller/service di titik kejadian domain (sama seperti ActivityLogger/
 * AuditLogger — tanpa event bus tambahan, supaya jelas kapan webhook terpicu
 * hanya dengan membaca kode di titik pemanggilan).
 */
class WebhookDispatcher
{
    public function dispatch(string $event, Model $subject, array $payload = []): void
    {
        $unitId = $subject->unit_id ?? null;

        if (! $unitId) {
            return;
        }

        Webhook::query()
            ->where('unit_id', $unitId)
            ->active()
            ->subscribedTo($event)
            ->each(fn (Webhook $webhook) => SendWebhookNotification::dispatch($webhook, $event, $payload));
    }
}
