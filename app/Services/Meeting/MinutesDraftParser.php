<?php

namespace App\Services\Meeting;

use RuntimeException;

/**
 * Parse draf notulen resmi dari balasan AI (objek JSON). Toleran terhadap
 * code fence, teks pembuka/penutup, dan field yang hilang atau salah tipe.
 */
class MinutesDraftParser
{
    /**
     * @return array{opening: ?string, discussion: list<array{topic: string, notes: string}>, decisions: list<string>, closing: ?string}
     */
    public function parse(string $content): array
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        $data = $start !== false && $end > $start ? json_decode(substr($content, $start, $end - $start + 1), true) : null;

        if (! is_array($data)) {
            throw new RuntimeException('AI tidak mengembalikan draf notulen yang valid.');
        }

        $text = fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $discussion = collect(is_array($data['pembahasan'] ?? null) ? $data['pembahasan'] : [])
            ->map(fn ($item) => is_array($item)
                ? ['topic' => $text($item['topik'] ?? null) ?? '', 'notes' => $text($item['uraian'] ?? null) ?? '']
                : ['topic' => '', 'notes' => $text($item) ?? ''])
            ->filter(fn (array $item) => $item['topic'] !== '' || $item['notes'] !== '')
            ->values()
            ->all();

        $decisions = collect(is_array($data['keputusan'] ?? null) ? $data['keputusan'] : [])
            ->map(fn ($item) => $text(is_array($item) ? ($item['keputusan'] ?? $item['uraian'] ?? null) : $item))
            ->filter()
            ->values()
            ->all();

        return [
            'opening' => $text($data['pembukaan'] ?? null),
            'discussion' => $discussion,
            'decisions' => $decisions,
            'closing' => $text($data['penutup'] ?? null),
        ];
    }
}
