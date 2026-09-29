<?php

namespace App\Services\Meeting;

/**
 * Memilih potongan transkrip yang relevan dengan pertanyaan, supaya chat rapat
 * berjam-jam tidak hanya "melihat" beberapa menit pertama. Pencarian kata kunci
 * sederhana (tanpa embedding): cukup untuk menemukan bagian rapat yang membahas
 * topik yang ditanyakan, dan hasilnya tetap berurutan waktu dengan label [HH:MM:SS].
 */
class TranscriptRetriever
{
    private const WINDOW_CHARS = 1500;

    /** Kata umum Bahasa Indonesia (dan beberapa Inggris) yang tidak membedakan topik. */
    private const STOPWORDS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'ini', 'itu', 'untuk', 'dengan', 'pada', 'adalah', 'ada', 'akan',
        'apa', 'apakah', 'siapa', 'kapan', 'mana', 'bagaimana', 'mengapa', 'kenapa', 'berapa', 'saja', 'juga',
        'tidak', 'bisa', 'sudah', 'belum', 'kita', 'kami', 'saya', 'anda', 'dia', 'mereka', 'rapat', 'tentang',
        'dalam', 'oleh', 'atau', 'karena', 'jadi', 'kalau', 'jika', 'soal', 'tadi', 'yg', 'nya', 'para', 'the',
        'what', 'who', 'when', 'how', 'is', 'are', 'of', 'to', 'a', 'in',
    ];

    /**
     * @return string transkrip utuh kalau muat dalam $budgetChars, selain itu
     *                potongan-potongan paling relevan (urut waktu) dipisah "[...]"
     */
    public function relevantExcerpt(string $transcript, string $question, int $budgetChars): string
    {
        if (mb_strlen($transcript) <= $budgetChars) {
            return $transcript;
        }

        $windows = $this->windows($transcript);
        $terms = $this->terms($question);

        $scored = [];
        foreach ($windows as $i => $window) {
            $scored[$i] = $this->score($window['text'], $terms);
        }

        // Tanpa kata kunci yang cocok, awal rapat (pembukaan/agenda) paling berguna.
        arsort($scored);
        $picked = [];
        $used = 0;
        foreach (array_keys($scored) as $i) {
            if ($scored[$i] <= 0 && $picked !== []) {
                break;
            }
            $length = mb_strlen($windows[$i]['text']) + 20;
            if ($used + $length > $budgetChars) {
                continue;
            }
            $picked[] = $i;
            $used += $length;
        }

        sort($picked);

        return collect($picked)
            ->map(fn (int $i) => trim(($windows[$i]['label'] ? $windows[$i]['label']."\n" : '').$windows[$i]['text']))
            ->implode("\n[...]\n");
    }

    /**
     * Pecah transkrip jadi jendela ±WINDOW_CHARS di batas baris; setiap jendela
     * membawa label waktu [HH:MM:SS] terakhir yang muncul sebelum/di dalamnya.
     *
     * @return list<array{label: ?string, text: string}>
     */
    private function windows(string $transcript): array
    {
        $windows = [];
        $current = '';
        $currentLabel = null;
        $lastLabel = null;

        foreach (preg_split('/\R/u', $transcript) as $line) {
            if (preg_match('/^\[\d{2}:\d{2}:\d{2}\]$/', trim($line))) {
                $lastLabel = trim($line);
                if ($current !== '') {
                    $windows[] = ['label' => $currentLabel, 'text' => $current];
                    $current = '';
                }
                $currentLabel = $lastLabel;

                continue;
            }

            // Baris yang sangat panjang (transkrip tanpa jeda baris) dipotong paksa.
            foreach (mb_str_split($line, self::WINDOW_CHARS) ?: [''] as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) > self::WINDOW_CHARS) {
                    $windows[] = ['label' => $currentLabel, 'text' => $current];
                    $current = '';
                    $currentLabel = $lastLabel;
                }
                $current .= ($current === '' ? '' : "\n").$piece;
            }
        }

        if (trim($current) !== '') {
            $windows[] = ['label' => $currentLabel, 'text' => $current];
        }

        return $windows;
    }

    /**
     * Kata kunci pertanyaan (tanpa kata umum), dipakai juga pencarian lintas rapat.
     *
     * @return list<string>
     */
    public function terms(string $question): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($question), $matches);

        return array_values(array_unique(array_filter(
            $matches[0],
            fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, self::STOPWORDS, true),
        )));
    }

    /**
     * @param  list<string>  $terms
     */
    public function score(string $text, array $terms): float
    {
        $haystack = mb_strtolower($text);
        $score = 0.0;

        foreach ($terms as $term) {
            $count = mb_substr_count($haystack, $term);
            // Istilah panjang (nama, kata teknis) lebih bermakna; kemunculan berulang
            // dihitung menurun supaya satu kata tidak mendominasi.
            $score += $count > 0 ? (1 + log($count)) * mb_strlen($term) : 0;
        }

        return $score;
    }
}
