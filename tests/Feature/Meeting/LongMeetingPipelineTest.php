<?php

namespace Tests\Feature\Meeting;

use App\Jobs\ProcessMeetingNotula;
use App\Models\Meeting;
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
use Tests\TestCase;

/**
 * Rapat 2 jam dari ujung ke ujung (antrean sync): 12 potongan 10 menit →
 * transkrip bertanda waktu → rangkuman bertingkat + action items per potongan →
 * chat yang menemukan pembahasan di menit ke-110.
 */
class LongMeetingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
        LongMeetingTextProvider::$prompts = [];

        Config::set('ai.providers.long_stt', ['driver' => LongMeetingTranscriber::class]);
        Config::set('ai.providers.long_text', ['driver' => LongMeetingTextProvider::class, 'model' => 'fake']);
        Config::set('ai.fallbacks', ['text' => [], 'transcription' => [], 'ocr' => []]);
        Config::set('ai.summary.chunk_chars', 20000);
        Setting::current()->update(['ai_transcription_provider' => 'long_stt', 'ai_text_provider' => 'long_text']);
        $this->app->instance(AudioSplitter::class, new LongMeetingSplitter);

        $unit = Unit::factory()->create();
        $this->user = User::factory()->create(['unit_id' => $unit->id]);
        $this->user->syncRoles(['user']);
    }

    public function test_two_hour_meeting_is_summarised_hierarchically_and_answerable_end_to_end(): void
    {
        Storage::disk('local')->put('recordings/rapat-2-jam.mp3', 'rekaman');
        $meeting = Meeting::create([
            'title' => 'Rapat Anggaran 2027', 'date' => now(), 'status' => 'Memproses', 'unit_id' => $this->user->unit_id,
            'user_id' => $this->user->id, 'source_disk' => 'local', 'source_file_path' => 'recordings/rapat-2-jam.mp3',
        ]);

        ProcessMeetingNotula::dispatch($meeting);

        $meeting->refresh();
        $this->assertSame('Selesai Diproses', $meeting->status);
        $this->assertSame(12, $meeting->processing_total_segments);
        $this->assertStringContainsString('[01:50:00]', $meeting->transcript);
        $this->assertGreaterThan(40000, mb_strlen($meeting->transcript));

        // Rangkuman bertingkat: catatan per potongan, lalu satu rangkuman final dari catatan itu.
        $notePrompts = array_filter(LongMeetingTextProvider::$prompts, fn ($p) => str_contains($p, 'Buat catatan poin-poin'));
        $this->assertGreaterThanOrEqual(3, count($notePrompts));
        foreach (LongMeetingTextProvider::$prompts as $prompt) {
            $this->assertLessThan(25000, mb_strlen($prompt), 'Tidak ada satu panggilan AI pun yang menerima transkrip utuh.');
        }
        $this->assertSame('<h3>Rangkuman rapat panjang</h3>', $meeting->summary);

        // Action items dari setiap potongan digabung; judul yang sama dihitung sekali.
        $titles = $meeting->actionItems()->pluck('title')->all();
        $this->assertContains('Finalisasi anggaran jembatan', $titles);
        $this->assertSame(count(array_unique(array_map('strtolower', $titles))), count($titles));

        // Chat menemukan pembahasan menjelang akhir rapat (dulu hanya 8.000 karakter pertama).
        LongMeetingTextProvider::$prompts = [];
        $this->actingAs($this->user)
            ->postJson(route('meetings.chat.store', $meeting), ['question' => 'Berapa anggaran jembatan Cisadane yang disepakati?'])
            ->assertOk();

        $chatPrompt = end(LongMeetingTextProvider::$prompts);
        $this->assertStringContainsString('anggaran jembatan Cisadane disepakati 4,2 miliar', $chatPrompt);
        $this->assertStringContainsString('[01:50:00]', $chatPrompt);
    }
}

class LongMeetingSplitter extends AudioSplitter
{
    public function __construct() {}

    public function split(string $sourcePath, string $outputDir): array
    {
        File::ensureDirectoryExists($outputDir);

        return collect(range(0, 11))->map(function (int $i) use ($outputDir) {
            $path = $outputDir.'/'.sprintf('part_%04d.mp3', $i);
            file_put_contents($path, 'audio');

            return ['path' => $path, 'start' => $i * 600.0, 'end' => ($i + 1) * 600.0];
        })->all();
    }
}

class LongMeetingTranscriber implements TranscriptionProvider
{
    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null, ?string $context = null): AiTranscriptionResult
    {
        $part = (int) substr($fileName, 5, 4);
        $text = str_repeat("Pembicara 1: pembahasan program kerja bagian {$part} berlanjut dengan rinci.\n", 55);
        if ($part === 11) {
            $text = "Siti: anggaran jembatan Cisadane disepakati 4,2 miliar.\n".$text;
        }

        return new AiTranscriptionResult(text: $text, provider: 'long_stt');
    }
}

class LongMeetingTextProvider implements TextGenerationProvider
{
    /** @var list<string> */
    public static array $prompts = [];

    public function __construct(private readonly ?string $model = null) {}

    public function generate(string $prompt): AiTextResult
    {
        self::$prompts[] = $prompt;

        $content = match (true) {
            str_contains($prompt, 'JSON array') => str_contains($prompt, 'Cisadane')
                ? '[{"title": "Finalisasi anggaran jembatan", "assignee_name": "Siti", "deadline": null}, {"title": "Susun laporan program kerja", "assignee_name": null, "deadline": null}]'
                : '[{"title": "Susun laporan program kerja", "assignee_name": null, "deadline": null}]',
            str_contains($prompt, 'Buat catatan poin-poin') => '- catatan bagian',
            str_contains($prompt, 'chronological notes') => '<h3>Rangkuman rapat panjang</h3>',
            default => 'Jawaban asisten',
        };

        return new AiTextResult(content: $content, provider: 'long_text', model: 'fake');
    }
}
