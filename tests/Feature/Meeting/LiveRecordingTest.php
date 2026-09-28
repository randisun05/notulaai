<?php

namespace Tests\Feature\Meeting;

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
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LiveRecordingTest extends TestCase
{
    use RefreshDatabase;

    private User $recorder;

    private User $colleague;

    private Meeting $meeting;

    private LiveFakeSplitter $splitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        LiveFakeTranscriber::$contexts = [];
        LiveFakeText::$prompts = [];

        Config::set('ai.providers.live_stt', ['driver' => LiveFakeTranscriber::class]);
        Config::set('ai.providers.live_text', ['driver' => LiveFakeText::class, 'model' => 'fake']);
        Config::set('ai.fallbacks', ['text' => [], 'transcription' => [], 'ocr' => []]);
        Setting::current()->update(['ai_transcription_provider' => 'live_stt', 'ai_text_provider' => 'live_text']);

        $this->splitter = new LiveFakeSplitter;
        $this->app->instance(AudioSplitter::class, $this->splitter);

        $unit = Unit::factory()->create();
        $this->recorder = User::factory()->create(['unit_id' => $unit->id]);
        $this->recorder->syncRoles(['user']);
        $this->colleague = User::factory()->create(['unit_id' => $unit->id]);
        $this->colleague->syncRoles(['user']);
        $this->meeting = Meeting::create([
            'title' => 'Rapat Koordinasi', 'date' => now(), 'status' => 'Dijadwalkan', 'unit_id' => $unit->id,
            'user_id' => $this->recorder->id, 'attendees' => 'Budi Santoso, Siti Aminah',
        ]);
    }

    private function start(?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->recorder)->postJson(route('meetings.live.start', $this->meeting), ['mime_type' => 'audio/webm;codecs=opus']);
    }

    /** Kirim audio sampai jam perekam menunjukkan $seconds (satu potongan tiap 5 detik). */
    private function recordUntil(float $seconds, ?User $as = null): void
    {
        $meeting = $this->meeting->fresh();
        $partSeconds = $meeting->live_recorded_seconds - $meeting->live_part_offset_seconds;
        $offset = $meeting->live_bytes;

        while ($partSeconds < $seconds - $meeting->live_part_offset_seconds - 0.001) {
            $partSeconds = min($partSeconds + 5, $seconds - $meeting->live_part_offset_seconds);
            $this->append($offset, $partSeconds, 'audio-bytes', $as)->assertOk();
            $offset += strlen('audio-bytes');
        }
    }

    private function append(int $offset, float $seconds, string $bytes, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->recorder)->call('PUT', route('meetings.live.append', $this->meeting), [], [], [], [
            'HTTP_X_UPLOAD_OFFSET' => (string) $offset,
            'HTTP_X_RECORDING_SECONDS' => (string) $seconds,
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_ACCEPT' => 'application/json',
        ], $bytes);
    }

    public function test_starting_a_live_recording(): void
    {
        $this->start()->assertOk()->assertJsonPath('status', 'Berlangsung')->assertJsonPath('received_bytes', 0);

        $meeting = $this->meeting->fresh();
        $this->assertSame($this->recorder->id, $meeting->live_user_id);
        Storage::disk('local')->assertExists("recordings/{$meeting->id}/live-1.webm");
        $this->assertDatabaseHas('activities', ['meeting_id' => $meeting->id, 'type' => 'meeting.live_started']);
    }

    public function test_audio_is_appended_in_order_and_only_by_the_recorder(): void
    {
        $this->start();
        $this->append(0, 5, 'aaaa')->assertOk()->assertJsonPath('received_bytes', 4);
        $this->append(4, 10, 'bbbb')->assertOk()->assertJsonPath('received_bytes', 8);

        // Balasan hilang, perekam mengirim ulang: server memberi posisi yang benar.
        $this->append(4, 10, 'bbbb')->assertStatus(409)->assertJsonPath('received_bytes', 8);
        $this->append(8, 15, 'cccc', $this->colleague)->assertForbidden();

        $this->assertSame('aaaabbbb', Storage::disk('local')->get("recordings/{$this->meeting->id}/live-1.webm"));
        $this->assertEqualsWithDelta(10, $this->meeting->fresh()->live_recorded_seconds, 0.001);
    }

    public function test_windows_are_cut_at_a_pause_between_60_and_90_seconds_and_transcribed_during_the_meeting(): void
    {
        $this->splitter->silences = [67.0];
        $this->start();

        $this->recordUntil(55);
        $this->assertSame(0, MeetingSegment::count(), 'Belum 60 detik: belum dipotong.');

        $this->recordUntil(70);

        $segment = MeetingSegment::sole();
        $this->assertEqualsWithDelta(0, $segment->start_seconds, 0.001);
        $this->assertEqualsWithDelta(67, $segment->end_seconds, 0.001, 'Dipotong di jeda, bukan tepat di detik 60.');
        // Dicari lagi tiap potongan 5 detik baru masuk, selalu mulai detik ke-60 jendela.
        $this->assertSame([[0.0, 60.0, 5.0], [0.0, 60.0, 10.0]], $this->splitter->searches);
        $this->assertSame('done', $segment->status);
        $this->assertSame('Berlangsung', $this->meeting->fresh()->status);
    }

    public function test_without_a_pause_it_waits_until_90_seconds_then_cuts(): void
    {
        $this->start();

        $this->recordUntil(85);
        $this->assertSame(0, MeetingSegment::count(), 'Tanpa jeda sebelum 90 detik: tunggu audio berikutnya.');

        $this->recordUntil(90);
        $this->assertEqualsWithDelta(90, MeetingSegment::sole()->end_seconds, 0.001);
    }

    public function test_speaker_context_carries_attendees_and_the_end_of_the_previous_window(): void
    {
        $this->splitter->silences = [65.0, 130.0];
        $this->start();

        $this->recordUntil(135);

        $this->assertCount(2, LiveFakeTranscriber::$contexts);
        $this->assertStringContainsString('Budi Santoso, Siti Aminah', LiveFakeTranscriber::$contexts[0]);
        $this->assertStringNotContainsString('Akhir potongan sebelumnya', LiveFakeTranscriber::$contexts[0]);
        // Hanya 6 baris terakhir (ai.live.context_lines) — cukup untuk kesinambungan pembicara.
        $this->assertStringContainsString("Akhir potongan sebelumnya:\nPembicara 1: bagian 0 kalimat 2", LiveFakeTranscriber::$contexts[1]);
        $this->assertStringEndsWith('Pembicara 1: bagian 0 kalimat 7', LiveFakeTranscriber::$contexts[1]);
        $this->assertStringNotContainsString('kalimat 1', LiveFakeTranscriber::$contexts[1]);
    }

    public function test_every_unit_member_sees_the_running_transcript_with_speaker_names(): void
    {
        $this->splitter->silences = [65.0];
        $this->start();
        $this->recordUntil(70);

        $this->actingAs($this->colleague)->putJson(route('meetings.speakers.rename', $this->meeting), ['from' => 'Pembicara 1', 'to' => 'Pak Budi'])->assertOk();
        // Penggantian berikutnya memakai nama yang sedang tampil.
        $this->actingAs($this->colleague)->putJson(route('meetings.speakers.rename', $this->meeting), ['from' => 'Pak Budi', 'to' => 'Budi Santoso'])->assertOk();
        $this->actingAs($this->colleague)->putJson(route('meetings.speakers.rename', $this->meeting), ['from' => 'Pembicara 2', 'to' => 'Bu: Siti'])->assertJsonValidationErrors('to');

        $this->actingAs($this->colleague)->get(route('meetings.show', $this->meeting))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('live.is_recorder', false)
                ->where('live.segments.0.label', '00:00:00')
                ->where('live.segments.0.text', fn ($text) => str_starts_with($text, 'Budi Santoso: bagian 0'))
                ->where('live.speakers.0', ['label' => 'Pembicara 1', 'name' => 'Budi Santoso']));
    }

    public function test_flagged_moments_are_timestamped_and_forced_into_the_minutes(): void
    {
        $this->splitter->silences = [65.0];
        $this->start();
        $this->recordUntil(70);

        $this->actingAs($this->colleague)->postJson(route('meetings.live.markers', $this->meeting), ['type' => 'keputusan', 'note' => 'Anggaran disetujui'])
            ->assertOk()
            ->assertJsonPath('at_seconds', 70);
        $this->actingAs($this->colleague)->postJson(route('meetings.live.markers', $this->meeting), ['type' => 'tindak_lanjut'])->assertOk();

        $this->recordUntil(80);
        $this->actingAs($this->recorder)->postJson(route('meetings.live.stop', $this->meeting))->assertOk();

        $summaryPrompt = collect(LiveFakeText::$prompts)->first(fn ($p) => str_contains($p, 'summarizes'));
        $this->assertStringContainsString('[00:01:10] Keputusan: Anggaran disetujui', $summaryPrompt);
        $this->assertStringContainsString('[00:01:10] Tindak Lanjut', $summaryPrompt);
        $actionPrompt = collect(LiveFakeText::$prompts)->first(fn ($p) => str_contains($p, 'JSON array'));
        $this->assertStringContainsString('Anggaran disetujui', $actionPrompt);
    }

    public function test_stopping_transcribes_the_tail_and_produces_the_minutes(): void
    {
        $this->splitter->silences = [65.0];
        $this->start();
        $this->recordUntil(100);
        $this->actingAs($this->colleague)->putJson(route('meetings.speakers.rename', $this->meeting), ['from' => 'Pembicara 1', 'to' => 'Pak Budi']);

        $this->actingAs($this->recorder)->postJson(route('meetings.live.stop', $this->meeting))->assertOk();

        $meeting = $this->meeting->fresh();
        $this->assertSame('Selesai Diproses', $meeting->status);
        $this->assertSame(2, $meeting->segments()->count());
        $this->assertEqualsWithDelta(100, $meeting->segments()->where('index', 1)->value('end_seconds'), 0.001);
        $this->assertStringContainsString("[00:00:00]\nPak Budi: bagian 0", $meeting->transcript);
        $this->assertStringContainsString("[00:01:05]\nPak Budi: bagian 1", $meeting->transcript);
        $this->assertStringNotContainsString('Pembicara 1:', $meeting->transcript);
        $this->assertSame('<p>Notula</p>', $meeting->summary);
        $this->assertSame("recordings/{$meeting->id}/live-1.webm", $meeting->source_file_path);
        $this->assertDatabaseHas('activities', ['meeting_id' => $meeting->id, 'type' => 'meeting.live_stopped']);
    }

    public function test_a_closed_tab_resumes_into_a_new_part_without_losing_audio(): void
    {
        $this->splitter->silences = [65.0];
        $this->start();
        $this->recordUntil(80);

        // Browser tertutup lalu dibuka lagi: stream baru, file bagian baru.
        $this->actingAs($this->recorder)->postJson(route('meetings.live.resume', $this->meeting), ['mime_type' => 'audio/webm'])
            ->assertOk()
            ->assertJsonPath('part', 2)
            ->assertJsonPath('received_bytes', 0);

        $meeting = $this->meeting->fresh();
        $this->assertEqualsWithDelta(80, $meeting->live_part_offset_seconds, 0.001);
        $this->assertEqualsWithDelta(80, $meeting->live_cursor_seconds, 0.001, 'Sisa bagian 1 (65–80 dtk) langsung ditutup.');
        Storage::disk('local')->assertExists("recordings/{$meeting->id}/live-2.webm");

        // Posisi jeda palsu relatif terhadap file bagian; bagian 2 tidak punya jeda.
        $this->splitter->silences = [];
        $this->recordUntil(150);
        $this->actingAs($this->recorder)->postJson(route('meetings.live.stop', $this->meeting));

        $meeting->refresh();
        $this->assertSame('Selesai Diproses', $meeting->status);
        $this->assertSame([[0.0, 65.0], [65.0, 80.0], [80.0, 150.0]], $meeting->segments()->get()
            ->map(fn ($s) => [round($s->start_seconds, 3), round($s->end_seconds, 3)])->all());
        // Bagian kedua dipotong dari awal file bagian 2, bukan dari detik 80 file itu.
        $this->assertContains(['live-2.webm', 0.0, null], $this->splitter->extracts);
        $this->assertSame("recordings/{$meeting->id}/rekaman-lengkap.mp3", $meeting->source_file_path);
    }

    public function test_watchdog_processes_a_recording_whose_device_went_silent(): void
    {
        $this->splitter->silences = [65.0];
        $this->start();
        $this->recordUntil(70);
        Meeting::whereKey($this->meeting->id)->update(['processing_heartbeat_at' => now()->subMinutes(20)]);

        $this->artisan('meetings:fail-stuck')->assertExitCode(0);

        $meeting = $this->meeting->fresh();
        $this->assertSame('Selesai Diproses', $meeting->status);
        $this->assertStringContainsString('terputus', $meeting->activities()->where('type', 'meeting.live_stopped')->value('description'));
    }

    public function test_a_processed_meeting_cannot_start_live_recording_again(): void
    {
        $this->meeting->update(['status' => 'Selesai Diproses']);

        $this->start()->assertStatus(409);
    }
}

class LiveFakeSplitter extends AudioSplitter
{
    /** @var list<float> jeda (detik di dalam file bagian aktif) yang "ditemukan" */
    public array $silences = [];

    /** @var list<array{0: float, 1: float, 2: float}> [awal file lokal, cari-dari, panjang] */
    public array $searches = [];

    /** @var list<array{0: string, 1: float, 2: ?float}> */
    public array $extracts = [];

    public function __construct() {}

    public function findSilence(string $sourcePath, float $from, float $length): ?float
    {
        $this->searches[] = [0.0, round($from, 3), round($length, 3)];

        foreach ($this->silences as $silence) {
            if ($silence >= $from && $silence <= $from + $length) {
                return $silence;
            }
        }

        return null;
    }

    public function extract(string $sourcePath, float $start, ?float $duration, string $outputPath): void
    {
        $this->extracts[] = [basename($sourcePath), round($start, 3), $duration === null ? null : round($duration, 3)];
        File::ensureDirectoryExists(dirname($outputPath));
        file_put_contents($outputPath, 'mp3');
    }

    public function concat(array $sourcePaths, string $outputPath): void
    {
        file_put_contents($outputPath, 'gabungan');
    }
}

class LiveFakeTranscriber implements TranscriptionProvider
{
    /** @var list<?string> */
    public static array $contexts = [];

    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null, ?string $context = null): AiTranscriptionResult
    {
        self::$contexts[] = $context;
        $index = (int) substr($fileName, 5, 4);
        $lines = array_map(fn ($i) => "Pembicara 1: bagian {$index} kalimat {$i}", range(0, 7));

        return new AiTranscriptionResult(text: implode("\n", $lines), provider: 'live_stt');
    }
}

class LiveFakeText implements TextGenerationProvider
{
    /** @var list<string> */
    public static array $prompts = [];

    public function __construct(private readonly ?string $model = null) {}

    public function generate(string $prompt): AiTextResult
    {
        self::$prompts[] = $prompt;

        return new AiTextResult(content: str_contains($prompt, 'JSON array') ? '[]' : '<p>Notula</p>', provider: 'live_text', model: 'fake');
    }
}
