<?php

namespace Tests\Unit\Services;

use App\Services\Audio\AudioSplitter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class AudioSplitterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/audio-splitter-test-'.uniqid();
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_it_reads_segment_boundaries_and_drops_a_sub_second_tail(): void
    {
        $out = $this->dir.'/out';
        Process::fake(function () use ($out) {
            File::ensureDirectoryExists($out);
            foreach (['part_0000.mp3', 'part_0001.mp3', 'part_0002.mp3'] as $file) {
                file_put_contents("{$out}/{$file}", 'x');
            }
            file_put_contents("{$out}/segments.csv", "part_0000.mp3,0.000000,600.000000\npart_0001.mp3,600.000000,1200.050000\npart_0002.mp3,1200.050000,1200.120000\n");

            return Process::result();
        });
        file_put_contents($this->dir.'/in.mp3', 'x');

        $segments = (new AudioSplitter)->split($this->dir.'/in.mp3', $out);

        $this->assertSame([
            ['path' => "{$out}/part_0000.mp3", 'start' => 0.0, 'end' => 600.0],
            ['path' => "{$out}/part_0001.mp3", 'start' => 600.0, 'end' => 1200.05],
        ], $segments);
        $this->assertFileDoesNotExist("{$out}/part_0002.mp3");
    }

    public function test_ffmpeg_failure_is_reported_with_its_error_output(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'Invalid data found when processing input', exitCode: 1));
        file_put_contents($this->dir.'/in.mp3', 'bukan audio');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid data found when processing input');

        (new AudioSplitter)->split($this->dir.'/in.mp3', $this->dir.'/out');
    }

    /**
     * Integrasi dengan ffmpeg sungguhan — dilewati kalau ffmpeg (FFMPEG_BINARY) tidak terpasang.
     */
    public function test_real_ffmpeg_splits_a_recording_into_fixed_length_segments(): void
    {
        $ffmpeg = Config::get('ai.audio.ffmpeg_binary');
        if (! Process::run([$ffmpeg, '-version'])->successful()) {
            $this->markTestSkipped('ffmpeg tidak tersedia.');
        }

        // Video 25 detik (Zoom-like mp4 dengan track audio) -> potongan 10 detik.
        Process::run([$ffmpeg, '-hide_banner', '-loglevel', 'error', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=25',
            '-f', 'lavfi', '-i', 'color=c=black:s=64x64:d=25', '-shortest', '-y', $this->dir.'/rapat.mp4'])->throw();
        Config::set('ai.audio.segment_seconds', 10);

        $segments = (new AudioSplitter)->split($this->dir.'/rapat.mp4', $this->dir.'/out');

        $this->assertCount(3, $segments);
        $this->assertEqualsWithDelta(0, $segments[0]['start'], 0.1);
        $this->assertEqualsWithDelta(10, $segments[1]['start'], 0.1);
        $this->assertEqualsWithDelta(20, $segments[2]['start'], 0.1);
        $this->assertEqualsWithDelta(25, $segments[2]['end'], 0.2);
        foreach ($segments as $segment) {
            $this->assertFileExists($segment['path']);
        }
    }
}
