<?php

namespace App\Services\Meeting;

class EmailDraftParser
{
    /**
     * Parse respons AI (diharapkan JSON {"subject":..., "body":...}) menjadi draft email.
     * Kalau parsing gagal, seluruh respons dipakai sebagai body dengan subjek generik —
     * draft ini akan direview & bisa diedit manusia sebelum dikirim, jadi tidak perlu
     * dianggap gagal total hanya karena AI tidak mengikuti format JSON.
     *
     * @return array{subject: string, body: string}
     */
    public function parse(string $raw, string $fallbackSubject): array
    {
        $json = trim($raw);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $json, $matches)) {
            $json = $matches[1];
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded) && preg_match('/\{.*\}/s', $json, $matches)) {
            $decoded = json_decode($matches[0], true);
        }

        if (is_array($decoded) && !empty($decoded['body'])) {
            return [
                'subject' => !empty($decoded['subject']) ? (string) $decoded['subject'] : $fallbackSubject,
                'body' => (string) $decoded['body'],
            ];
        }

        return [
            'subject' => $fallbackSubject,
            'body' => nl2br(e(trim($raw))),
        ];
    }
}
