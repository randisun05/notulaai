<?php

namespace Tests\Unit\Services;

use App\Services\Meeting\TranscriptRetriever;
use PHPUnit\Framework\TestCase;

class TranscriptRetrieverTest extends TestCase
{
    /** Transkrip 2 jam: 12 bagian 10 menit, masing-masing ±4000 karakter obrolan umum. */
    private function longTranscript(array $specialLines = []): string
    {
        $blocks = [];
        for ($i = 0; $i < 12; $i++) {
            $label = sprintf('[%02d:%02d:00]', intdiv($i * 10, 60), ($i * 10) % 60);
            $body = str_repeat("Pembicara 1: kita lanjutkan pembahasan program kerja bagian {$i}.\n", 60);
            $blocks[] = $label."\n".($specialLines[$i] ?? '').$body;
        }

        return implode("\n\n", $blocks);
    }

    public function test_short_transcripts_are_returned_whole(): void
    {
        $this->assertSame('Budi: halo', (new TranscriptRetriever)->relevantExcerpt('Budi: halo', 'apa kata Budi?', 1000));
    }

    public function test_it_finds_the_passage_near_the_end_of_a_long_meeting(): void
    {
        $transcript = $this->longTranscript([
            11 => "Siti: anggaran jembatan Cisadane disepakati 4,2 miliar.\n",
        ]);

        $excerpt = (new TranscriptRetriever)->relevantExcerpt($transcript, 'Berapa anggaran jembatan Cisadane?', 6000);

        $this->assertStringContainsString('anggaran jembatan Cisadane disepakati 4,2 miliar', $excerpt);
        $this->assertStringContainsString('[01:50:00]', $excerpt);
        $this->assertLessThanOrEqual(6000, mb_strlen($excerpt));
    }

    public function test_excerpts_from_several_parts_stay_in_chronological_order(): void
    {
        $transcript = $this->longTranscript([
            9 => "Andi: laporan Cisadane direvisi.\n",
            2 => "Budi: survei Cisadane dimulai.\n",
        ]);

        $excerpt = (new TranscriptRetriever)->relevantExcerpt($transcript, 'bagaimana progres Cisadane', 6000);

        $this->assertLessThan(strpos($excerpt, 'laporan Cisadane'), strpos($excerpt, 'survei Cisadane'));
        $this->assertStringContainsString('[00:20:00]', $excerpt);
        $this->assertStringContainsString('[01:30:00]', $excerpt);
    }

    public function test_without_matching_terms_it_falls_back_to_some_context_within_budget(): void
    {
        $excerpt = (new TranscriptRetriever)->relevantExcerpt($this->longTranscript(), 'apa itu?', 3000);

        $this->assertNotSame('', $excerpt);
        $this->assertLessThanOrEqual(3000, mb_strlen($excerpt));
    }
}
