<?php

namespace Tests\Unit\Services;

use App\Services\Meeting\MinutesDraftParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MinutesDraftParserTest extends TestCase
{
    public function test_it_reads_json_wrapped_in_prose_and_code_fences(): void
    {
        $parsed = (new MinutesDraftParser)->parse("Tentu, ini drafnya:\n```json\n{\"pembukaan\": \" Dibuka. \", \"pembahasan\": [{\"topik\": \"A\", \"uraian\": \"B\"}], \"keputusan\": [\"K1\"], \"penutup\": \"Ditutup.\"}\n```\nSemoga membantu.");

        $this->assertSame([
            'opening' => 'Dibuka.',
            'discussion' => [['topic' => 'A', 'notes' => 'B']],
            'decisions' => ['K1'],
            'closing' => 'Ditutup.',
        ], $parsed);
    }

    public function test_it_tolerates_missing_fields_and_odd_shapes(): void
    {
        $parsed = (new MinutesDraftParser)->parse('{"pembahasan": ["uraian tanpa topik", {"topik": ""}, 5], "keputusan": [{"keputusan": "K1"}, "", null]}');

        $this->assertNull($parsed['opening']);
        $this->assertSame([['topic' => '', 'notes' => 'uraian tanpa topik']], $parsed['discussion']);
        $this->assertSame(['K1'], $parsed['decisions']);
        $this->assertNull($parsed['closing']);
    }

    public function test_it_rejects_a_reply_without_json(): void
    {
        $this->expectException(RuntimeException::class);

        (new MinutesDraftParser)->parse('Maaf, saya tidak bisa membantu.');
    }
}
