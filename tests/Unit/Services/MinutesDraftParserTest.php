<?php

namespace Tests\Unit\Services;

use App\Services\Meeting\MinutesDraftParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MinutesDraftParserTest extends TestCase
{
    public function test_it_reads_json_wrapped_in_prose_and_code_fences(): void
    {
        $parsed = (new MinutesDraftParser)->parse("Tentu, ini drafnya:\n```json\n{\"resume\": [{\"pembicara\": null, \"isi\": \" Rapat dibuka. \", \"tanggapan\": \"\"}, {\"pembicara\": \"Ika, Dit. Bangtarier\", \"isi\": \"Pasal 15?\", \"tanggapan\": \"Tetap.\"}], \"kesimpulan\": [\"K1\"]}\n```\nSemoga membantu.");

        $this->assertSame([
            'resume' => [
                ['speaker' => null, 'text' => 'Rapat dibuka.', 'response' => null],
                ['speaker' => 'Ika, Dit. Bangtarier', 'text' => 'Pasal 15?', 'response' => 'Tetap.'],
            ],
            'decisions' => ['K1'],
        ], $parsed);
    }

    public function test_it_tolerates_odd_shapes(): void
    {
        $parsed = (new MinutesDraftParser)->parse('{"resume": ["poin berupa teks", {"pembicara": "X"}, 5], "kesimpulan": [{"isi": "K1"}, "", null]}');

        $this->assertSame([['speaker' => null, 'text' => 'poin berupa teks', 'response' => null]], $parsed['resume']);
        $this->assertSame(['K1'], $parsed['decisions']);
    }

    public function test_it_rejects_a_reply_without_json_or_without_resume(): void
    {
        foreach (['Maaf, saya tidak bisa membantu.', '{"kesimpulan": ["K1"]}'] as $reply) {
            try {
                (new MinutesDraftParser)->parse($reply);
                $this->fail("Seharusnya ditolak: {$reply}");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
