<?php

namespace App\Services\Meeting;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ActionItemsParser
{
    /**
     * Parse respons AI (yang diharapkan berupa JSON array) menjadi daftar action item.
     * Selalu mengembalikan array, tidak pernah melempar exception — respons AI yang
     * tidak valid dianggap "tidak ada action item" daripada menggagalkan pemrosesan.
     *
     * @return array<int, array{title: string, assignee_name: ?string, deadline: ?string}>
     */
    public function parse(string $raw): array
    {
        $json = trim($raw);

        // Model kadang tetap membungkus JSON dalam code fence meski sudah diminta tidak.
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $json, $matches)) {
            $json = $matches[1];
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded) && preg_match('/\[.*\]/s', $json, $matches)) {
            $decoded = json_decode($matches[0], true);
        }

        if (!is_array($decoded)) {
            Log::warning('Respons action items bukan JSON yang valid, dilewati.', ['raw' => $raw]);

            return [];
        }

        return collect($decoded)
            ->filter(fn ($item) => is_array($item) && !empty($item['title']))
            ->map(fn ($item) => [
                'title' => (string) $item['title'],
                'assignee_name' => !empty($item['assignee_name']) ? (string) $item['assignee_name'] : null,
                'deadline' => $this->parseDeadline($item['deadline'] ?? null),
            ])
            ->values()
            ->all();
    }

    private function parseDeadline(mixed $value): ?string
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }
}
