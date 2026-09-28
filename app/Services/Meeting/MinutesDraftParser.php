<?php

namespace App\Services\Meeting;

use RuntimeException;

/**
 * Parse draf notula dari balasan AI (objek JSON). Toleran terhadap code fence,
 * teks pembuka/penutup, dan field yang hilang atau salah tipe.
 */
class MinutesDraftParser
{
    /**
     * @return array{resume: list<array{speaker: ?string, text: string, response: ?string}>, decisions: list<string>}
     */
    public function parse(string $content): array
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        $data = $start !== false && $end > $start ? json_decode(substr($content, $start, $end - $start + 1), true) : null;

        if (! is_array($data)) {
            throw new RuntimeException('AI tidak mengembalikan draf notula yang valid.');
        }

        $text = fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $resume = collect(is_array($data['resume'] ?? null) ? $data['resume'] : [])
            ->map(fn ($item) => is_array($item)
                ? ['speaker' => $text($item['pembicara'] ?? null), 'text' => $text($item['isi'] ?? null) ?? '', 'response' => $text($item['tanggapan'] ?? null)]
                : ['speaker' => null, 'text' => $text($item) ?? '', 'response' => null])
            ->filter(fn (array $item) => $item['text'] !== '')
            ->values()
            ->all();

        if ($resume === []) {
            throw new RuntimeException('Draf notula dari AI tidak berisi resume.');
        }

        $decisions = collect(is_array($data['kesimpulan'] ?? null) ? $data['kesimpulan'] : [])
            ->map(fn ($item) => $text(is_array($item) ? ($item['kesimpulan'] ?? $item['isi'] ?? null) : $item))
            ->filter()
            ->values()
            ->all();

        return ['resume' => $resume, 'decisions' => $decisions];
    }
}
