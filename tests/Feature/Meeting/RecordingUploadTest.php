<?php

namespace Tests\Feature\Meeting;

use App\Jobs\ProcessMeetingNotula;
use App\Models\Meeting;
use App\Models\RecordingUpload;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordingUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Bus::fake();
        Config::set('ai.audio.chunk_bytes', 1000);
        Config::set('ai.audio.max_chunk_bytes', 1500);

        $unit = Unit::factory()->create();
        $this->user = User::factory()->create(['unit_id' => $unit->id]);
        $this->user->syncRoles(['user']);
        $this->meeting = Meeting::create(['title' => 'Rapat', 'date' => now(), 'status' => 'Dijadwalkan', 'unit_id' => $unit->id, 'user_id' => $this->user->id]);
    }

    /** WAV PCM mono 8 kHz yang valid (dikenali finfo sebagai audio/x-wav). */
    private function wav(int $dataBytes): string
    {
        return 'RIFF'.pack('V', 36 + $dataBytes).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
            .'data'.pack('V', $dataBytes).str_repeat("\0", $dataBytes);
    }

    private function init(string $bytes, string $name = 'rapat.wav')
    {
        return $this->actingAs($this->user)->postJson(route('meetings.recording-uploads.store', $this->meeting), [
            'file_name' => $name,
            'file_size' => strlen($bytes),
        ]);
    }

    private function append(string $id, string $chunk, int $offset)
    {
        return $this->actingAs($this->user)->call('PUT', route('recording-uploads.append', $id), [], [], [], [
            'HTTP_X_UPLOAD_OFFSET' => (string) $offset,
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_ACCEPT' => 'application/json',
        ], $chunk);
    }

    private function uploadAll(string $id, string $bytes): void
    {
        foreach (str_split($bytes, 1000) as $i => $chunk) {
            $this->append($id, $chunk, $i * 1000)->assertOk();
        }
    }

    public function test_recording_is_uploaded_in_chunks_then_processed_from_the_private_disk(): void
    {
        $bytes = $this->wav(2500);
        $id = $this->init($bytes)->assertCreated()->assertJsonPath('received_bytes', 0)->json('id');

        $this->uploadAll($id, $bytes);
        $this->actingAs($this->user)->postJson(route('recording-uploads.complete', $id))
            ->assertOk()
            ->assertJsonPath('status', 'Memproses');

        $meeting = $this->meeting->fresh();
        $this->assertSame('local', $meeting->source_disk);
        $this->assertStringStartsWith("recordings/{$meeting->id}/", $meeting->source_file_path);
        $this->assertSame($bytes, Storage::disk('local')->get($meeting->source_file_path));
        $this->assertDatabaseCount('recording_uploads', 0);
        Bus::assertDispatched(ProcessMeetingNotula::class);
    }

    public function test_choosing_the_same_file_again_resumes_where_it_stopped(): void
    {
        $bytes = $this->wav(2500);
        $id = $this->init($bytes)->json('id');
        $this->append($id, substr($bytes, 0, 1000), 0)->assertOk();

        $this->init($bytes)->assertOk()->assertJsonPath('id', $id)->assertJsonPath('received_bytes', 1000);
    }

    public function test_a_chunk_at_the_wrong_offset_is_refused_with_the_server_position(): void
    {
        $bytes = $this->wav(2500);
        $id = $this->init($bytes)->json('id');
        $this->append($id, substr($bytes, 0, 1000), 0)->assertOk();

        // Respons pertama "hilang", klien mengirim ulang potongan yang sama.
        $this->append($id, substr($bytes, 0, 1000), 0)->assertStatus(409)->assertJsonPath('received_bytes', 1000);

        $this->assertSame(1000, RecordingUpload::find($id)->received_bytes);
    }

    public function test_an_oversized_chunk_is_refused_and_not_counted(): void
    {
        $bytes = $this->wav(5000);
        $id = $this->init($bytes)->json('id');

        $this->append($id, substr($bytes, 0, 2000), 0)->assertStatus(422);

        $this->assertSame(0, RecordingUpload::find($id)->received_bytes);
        // Potongan berikutnya yang benar tetap diterima dari offset 0 (sisa tulisan dibuang).
        $this->append($id, substr($bytes, 0, 1000), 0)->assertOk()->assertJsonPath('received_bytes', 1000);
    }

    public function test_completing_an_unfinished_upload_is_refused(): void
    {
        $bytes = $this->wav(2500);
        $id = $this->init($bytes)->json('id');
        $this->append($id, substr($bytes, 0, 1000), 0);

        $this->actingAs($this->user)->postJson(route('recording-uploads.complete', $id))->assertStatus(422);
        Bus::assertNothingDispatched();
    }

    public function test_content_that_is_not_audio_is_rejected_on_completion(): void
    {
        $bytes = str_repeat('bukan audio ', 100);
        $id = $this->init($bytes, 'rapat.mp3')->json('id');
        $this->uploadAll($id, $bytes);

        $this->actingAs($this->user)->postJson(route('recording-uploads.complete', $id))->assertStatus(422);

        $this->assertDatabaseCount('recording_uploads', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame('Dijadwalkan', $this->meeting->fresh()->status);
    }

    public function test_unsupported_extension_and_oversized_files_are_refused_up_front(): void
    {
        $this->actingAs($this->user)->postJson(route('meetings.recording-uploads.store', $this->meeting), ['file_name' => 'dokumen.pdf', 'file_size' => 10])
            ->assertJsonValidationErrors('file_name');

        Config::set('ai.audio.max_upload_mb', 1);
        $this->actingAs($this->user)->postJson(route('meetings.recording-uploads.store', $this->meeting), ['file_name' => 'rapat.mp3', 'file_size' => 2 * 1024 * 1024])
            ->assertJsonValidationErrors('file_size');
    }

    public function test_another_user_cannot_write_to_someone_elses_upload(): void
    {
        $bytes = $this->wav(2500);
        $id = $this->init($bytes)->json('id');
        $colleague = User::factory()->create(['unit_id' => $this->user->unit_id]);
        $colleague->syncRoles(['user']);

        $this->actingAs($colleague)->call('PUT', route('recording-uploads.append', $id), [], [], [], [
            'HTTP_X_UPLOAD_OFFSET' => '0', 'HTTP_ACCEPT' => 'application/json',
        ], 'x')->assertForbidden();
    }

    public function test_uploads_cannot_start_for_a_meeting_that_is_already_processed(): void
    {
        $this->meeting->update(['status' => 'Selesai Diproses']);

        $this->init($this->wav(100))->assertStatus(409);
    }

    public function test_recording_is_streamed_only_to_users_who_can_view_the_meeting(): void
    {
        Storage::disk('local')->put("recordings/{$this->meeting->id}/a.wav", $this->wav(100));
        $this->meeting->update(['source_disk' => 'local', 'source_file_path' => "recordings/{$this->meeting->id}/a.wav"]);

        $this->actingAs($this->user)->get(route('meetings.recording', $this->meeting))->assertOk();

        $outsider = User::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        $outsider->syncRoles(['user']);
        $this->actingAs($outsider)->get(route('meetings.recording', $this->meeting))->assertForbidden();
    }

    public function test_abandoned_uploads_are_pruned(): void
    {
        $bytes = $this->wav(2500);
        $id = $this->init($bytes)->json('id');
        $this->append($id, substr($bytes, 0, 1000), 0);
        RecordingUpload::whereKey($id)->update(['updated_at' => now()->subDays(3)]);
        Storage::disk('local')->put('recording_uploads/yatim.part', 'x');

        $this->artisan('recordings:prune-uploads')->assertExitCode(0);

        $this->assertDatabaseCount('recording_uploads', 0);
        $this->assertSame([], Storage::disk('local')->files('recording_uploads'));
    }

    public function test_deleting_the_meeting_removes_private_recordings_and_partial_uploads(): void
    {
        $admin = User::factory()->create(['unit_id' => $this->user->unit_id]);
        $admin->syncRoles(['admin']);
        $bytes = $this->wav(2500);
        $id = $this->init($bytes)->json('id');
        $this->append($id, substr($bytes, 0, 1000), 0);
        Storage::disk('local')->put("recordings/{$this->meeting->id}/lama.wav", 'x');
        Storage::disk('local')->put("meeting_segments/{$this->meeting->id}/part_0000.mp3", 'x');

        $this->actingAs($admin)->delete(route('meetings.destroy', $this->meeting))->assertRedirect();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
