<?php

namespace Tests\Feature\Memory;

use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;

/**
 * Text AI palsu untuk fitur memori lintas rapat: jawabannya dipilih dari isi prompt.
 */
class MemoryFakeText implements TextGenerationProvider
{
    /** @var list<string> */
    public static array $prompts = [];

    public static string $answer = 'Menurut rapat [1], anggaran jembatan ditetapkan Rp4,2 miliar.';

    public function __construct(private readonly ?string $model = null) {}

    public function generate(string $prompt): AiTextResult
    {
        self::$prompts[] = $prompt;

        $content = match (true) {
            str_contains($prompt, 'KEPUTUSAN / KESIMPULAN') => "```json\n".json_encode([
                ['keputusan' => 'Anggaran jembatan ditetapkan Rp4,2 miliar', 'tindak_lanjut' => 1],
                ['keputusan' => 'Rapat evaluasi diadakan tiap bulan', 'tindak_lanjut' => null],
            ])."\n```",
            str_contains($prompt, 'action item') => json_encode([
                ['title' => 'Susun RAB final jembatan', 'assignee_name' => 'Budi', 'deadline' => '2026-10-20'],
            ]),
            str_contains($prompt, 'SUMBER RAPAT') => self::$answer,
            default => '<h3>Ringkasan</h3><p>Anggaran jembatan disepakati Rp4,2 miliar.</p>',
        };

        return new AiTextResult(content: $content, provider: 'memory_text', model: 'fake');
    }
}
