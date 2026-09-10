<?php

namespace Tests\Unit\Services;

use App\Services\AI\Providers\WhisperLocalTranscriptionProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WhisperLocalTranscriptionProviderTest extends TestCase
{
    private string $audioPath;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.stt_service.url', 'http://whisper:5055/transcribe');

        $this->audioPath = tempnam(sys_get_temp_dir(), 'stt').'.mp3';
        file_put_contents($this->audioPath, 'fake audio bytes');
    }

    protected function tearDown(): void
    {
        @unlink($this->audioPath);

        parent::tearDown();
    }

    public function test_returns_transcript_on_success(): void
    {
        Http::fake([
            '*' => Http::response(['text' => 'halo dunia', 'language' => 'id']),
        ]);

        $result = (new WhisperLocalTranscriptionProvider)->transcribe($this->audioPath, 'rapat.mp3', 'Indonesian');

        $this->assertSame('halo dunia', $result->text);
        $this->assertSame('id', $result->language);
        $this->assertSame('whisper_local', $result->provider);
    }

    public function test_connection_failure_reports_service_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Connection refused'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak dapat dihubungi');

        (new WhisperLocalTranscriptionProvider)->transcribe($this->audioPath, 'rapat.mp3');
    }

    public function test_non_200_response_surfaces_the_service_error_body(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'ffmpeg failed to decode input'], 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ffmpeg failed to decode input');

        (new WhisperLocalTranscriptionProvider)->transcribe($this->audioPath, 'rapat.mp3');
    }

    public function test_empty_transcript_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response(['text' => '', 'language' => 'id']),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kosong');

        (new WhisperLocalTranscriptionProvider)->transcribe($this->audioPath, 'rapat.mp3');
    }
}
