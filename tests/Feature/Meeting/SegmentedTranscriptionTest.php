<?php

namespace Tests\Feature\Meeting;

use App\Jobs\ProcessMeetingNotula;
use App\Models\Meeting;
use App\Models\MeetingSegment;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\Contracts\TranscriptionProvider;
use App\Services\AI\DTO\AiTextResult;
use App\Services\AI\DTO\AiTranscriptionResult;
use App\Services\Audio\AudioSplitter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class SegmentedTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('local');

        FakeSegmentTranscriber::$calls = [];
        FakeSegmentTranscriber::$failOn = null;

        Config::set('ai.providers.fake_stt', ['driver' => FakeSegmentTranscriber::class]);
        Config::set('ai.providers.fake_text', ['driver' => FakeSummaryText::class, 'model' => 'fake']);
        Config::set('ai.fallbacks', ['text' => [], 'transcription' => [], 'ocr' => []]);
        Setting::current()->update(['ai_transcription_provider' => 'fake_stt', 'ai_text_provider' => 'fake_text']);

        // 3 potongan: 0-600, 600-1200, 1200-1500 detik (rekaman 25 menit).
        $this->app->instance(AudioSplitter::class, new FakeAudioSplitter([[0, 600], [600, 1200], [1200, 1500]]));

        $this->unit = Unit::factory()->create();
        $this->user = User::factory()->create(['unit_id' => $this->unit->id]);
        $this->user->syncRoles(['user']);
    }

    private function meetingWithRecording(): Meeting
    {
        Storage::disk('public')->put('audio_uploads/rapat.mp3', 'rekaman');

        return Meeting::create([
            'title' => 'Rapat panjang', 'date' => now(), 'status' => 'Memproses', 'unit_id' => $this->unit->id,
            'user_id' => $this->user->id, 'source_file_path' => 'audio_uploads/rapat.mp3',
        ]);
    }

    public function test_recording_is_transcribed_per_segment_and_joined_with_timestamps(): void
    {
        $meeting = $this->meetingWithRecording();

        ProcessMeetingNotula::dispatch($meeting);

        $meeting->refresh();
        $this->assertSame('Selesai Diproses', $meeting->status);
        $this->assertNull($meeting->processing_stage);
        $this->assertSame(3, $meeting->processing_total_segments);
        $this->assertSame(['part_0000.mp3', 'part_0001.mp3', 'part_0002.mp3'], FakeSegmentTranscriber::$calls);
        $this->assertSame(
            "[00:00:00]\nteks part_0000.mp3\n\n[00:10:00]\nteks part_0001.mp3\n\n[00:20:00]\nteks part_0002.mp3",
            $meeting->transcript,
        );
        $this->assertSame(3, $meeting->segments()->where('status', MeetingSegment::STATUS_DONE)->count());
        $this->assertSame([], Storage::disk('local')->allFiles("meeting_segments/{$meeting->id}"));
        $this->assertDatabaseHas('activities', ['meeting_id' => $meeting->id, 'type' => 'meeting.processed']);
    }

    public function test_a_segment_that_keeps_failing_fails_the_meeting_once(): void
    {
        FakeSegmentTranscriber::$failOn = 'part_0001.mp3';
        $meeting = $this->meetingWithRecording();

        try {
            ProcessMeetingNotula::dispatch($meeting);
        } catch (RuntimeException) {
            // Antrean sync melempar ulang exception job setelah failed() dipanggil.
        }

        $meeting->refresh();
        $this->assertSame('Gagal', $meeting->status);
        $this->assertSame(1, $meeting->activities()->where('type', 'meeting.failed')->count());
        $this->assertStringContainsString('00:10:00', $meeting->activities()->where('type', 'meeting.failed')->value('description'));
        $this->assertSame(MeetingSegment::STATUS_FAILED, $meeting->segments()->where('index', 1)->value('status'));
    }

    public function test_reprocessing_after_failure_starts_from_scratch(): void
    {
        $meeting = $this->meetingWithRecording();
        MeetingSegment::create(['meeting_id' => $meeting->id, 'index' => 0, 'start_seconds' => 0, 'end_seconds' => 5, 'status' => 'failed']);

        ProcessMeetingNotula::dispatch($meeting);

        $this->assertSame(3, $meeting->segments()->count());
        $this->assertSame('Selesai Diproses', $meeting->fresh()->status);
    }

    public function test_show_page_exposes_segment_progress(): void
    {
        $meeting = $this->meetingWithRecording();
        $meeting->update(['processing_stage' => 'transcribing', 'processing_total_segments' => 3]);
        MeetingSegment::create(['meeting_id' => $meeting->id, 'index' => 0, 'start_seconds' => 0, 'end_seconds' => 600, 'status' => 'done']);
        MeetingSegment::create(['meeting_id' => $meeting->id, 'index' => 1, 'start_seconds' => 600, 'end_seconds' => 1200]);

        $this->actingAs($this->user)->get(route('meetings.show', $meeting))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('progress.status', 'Memproses')
                ->where('progress.stage', 'transcribing')
                ->where('progress.done', 1)
                ->where('progress.total', 3));
    }

    public function test_watchdog_leaves_a_long_meeting_alone_while_it_keeps_making_progress(): void
    {
        $meeting = $this->meetingWithRecording();
        Meeting::whereKey($meeting->id)->update(['updated_at' => now()->subHours(2), 'processing_heartbeat_at' => now()->subMinutes(3)]);

        $this->artisan('meetings:fail-stuck', ['--minutes' => 20])->assertExitCode(0);

        $this->assertSame('Memproses', $meeting->fresh()->status);
    }

    public function test_watchdog_fails_a_meeting_without_progress(): void
    {
        $meeting = $this->meetingWithRecording();
        Meeting::whereKey($meeting->id)->update(['processing_heartbeat_at' => now()->subMinutes(30)]);

        $this->artisan('meetings:fail-stuck', ['--minutes' => 20])->assertExitCode(0);

        $this->assertSame('Gagal', $meeting->fresh()->status);
    }
}

class FakeAudioSplitter extends AudioSplitter
{
    /** @param  list<array{0: int, 1: int}>  $ranges */
    public function __construct(private readonly array $ranges) {}

    public function split(string $sourcePath, string $outputDir): array
    {
        File::ensureDirectoryExists($outputDir);

        return collect($this->ranges)->map(function (array $range, int $i) use ($outputDir) {
            $path = $outputDir.'/'.sprintf('part_%04d.mp3', $i);
            file_put_contents($path, 'audio');

            return ['path' => $path, 'start' => (float) $range[0], 'end' => (float) $range[1]];
        })->all();
    }
}

class FakeSegmentTranscriber implements TranscriptionProvider
{
    /** @var list<string> */
    public static array $calls = [];

    public static ?string $failOn = null;

    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null, ?string $context = null): AiTranscriptionResult
    {
        if ($fileName === self::$failOn) {
            throw new RuntimeException('STT tidak tersedia');
        }

        self::$calls[] = $fileName;

        return new AiTranscriptionResult(text: "teks {$fileName}", provider: 'fake_stt');
    }
}

class FakeSummaryText implements TextGenerationProvider
{
    public function __construct(private readonly ?string $model = null) {}

    public function generate(string $prompt): AiTextResult
    {
        $content = str_contains($prompt, 'JSON array') ? '[]' : '<p>Rangkuman</p>';

        return new AiTextResult(content: $content, provider: 'fake_text', model: 'fake');
    }
}
