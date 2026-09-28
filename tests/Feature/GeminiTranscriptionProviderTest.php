<?php

namespace Tests\Feature;

use App\Services\AI\Providers\GeminiTranscriptionProvider;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Resources\GenerativeModel;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class GeminiTranscriptionProviderTest extends TestCase
{
    private string $audio;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('gemini.api_key', 'test-key');
        $this->audio = tempnam(sys_get_temp_dir(), 'seg').'.mp3';
        file_put_contents($this->audio, 'fake-mp3-bytes');
    }

    protected function tearDown(): void
    {
        @unlink($this->audio);

        parent::tearDown();
    }

    public function test_it_sends_the_audio_inline_and_returns_the_transcript(): void
    {
        Gemini::fake([
            GenerateContentResponse::fake(['candidates' => [['content' => ['parts' => [['text' => "  Budi: Selamat pagi.\nSiti: Pagi.  "]]]]]]),
        ]);

        $result = (new GeminiTranscriptionProvider('gemini-test'))->transcribe($this->audio, 'part_0000.mp3', 'Indonesian');

        $this->assertSame("Budi: Selamat pagi.\nSiti: Pagi.", $result->text);
        $this->assertSame('gemini_stt', $result->provider);

        Gemini::assertSent(GenerativeModel::class, callback: function (string $method, array $parameters) {
            $blob = collect($parameters)->flatten()->first(fn ($part) => $part instanceof Blob);

            return $method === 'generateContent'
                && $blob?->mimeType === MimeType::AUDIO_MP3
                && $blob->data === base64_encode('fake-mp3-bytes');
        });
    }

    public function test_it_refuses_formats_gemini_cannot_take_inline(): void
    {
        $this->expectException(RuntimeException::class);

        (new GeminiTranscriptionProvider('gemini-test'))->transcribe($this->audio, 'rapat.mkv');
    }
}
