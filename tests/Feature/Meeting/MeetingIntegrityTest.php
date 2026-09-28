<?php

namespace Tests\Feature\Meeting;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MeetingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userInUnit(Unit $unit, string $role = 'user'): User
    {
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function meeting(Unit $unit, User $creator, array $attributes = []): Meeting
    {
        return Meeting::create([
            'title' => 'Rapat',
            'date' => now(),
            'status' => 'Dijadwalkan',
            'unit_id' => $unit->id,
            'user_id' => $creator->id,
            ...$attributes,
        ]);
    }

    public function test_meeting_can_be_scheduled_without_agenda_or_attendees(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);

        $this->actingAs($user)->post(route('meetings.store'), [
            'title' => 'Rapat singkat',
            'date' => '2026-10-01 09:30',
            'agenda' => '',
            'attendees' => '',
        ])->assertRedirect(route('meetings.index'));

        $this->assertDatabaseHas('meetings', ['title' => 'Rapat singkat']);
    }

    public function test_search_never_returns_meetings_from_another_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $outsider = $this->userInUnit($otherUnit);

        $this->meeting($unit, $user, ['title' => 'Rapat anggaran unit sendiri']);
        // Cocok lewat agenda/transkrip/rangkuman, bukan judul — kolom yang dulu
        // di-OR tanpa dikelompokkan sehingga lolos dari filter unit.
        $this->meeting($otherUnit, $outsider, ['title' => 'Rahasia', 'agenda' => 'anggaran', 'transcript' => 'anggaran', 'summary' => 'anggaran']);

        $this->actingAs($user)
            ->get(route('meetings.index', ['search' => 'anggaran']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('meetings.data', 1)
                ->where('meetings.data.0.title', 'Rapat anggaran unit sendiri'));
    }

    public function test_meeting_already_processing_cannot_be_processed_again(): void
    {
        Bus::fake();
        Storage::fake('public');
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $meeting = $this->meeting($unit, $user, ['status' => 'Memproses']);

        $this->actingAs($user)->post(route('meetings.process', $meeting), [
            'type' => 'text',
            'text_input' => 'Transkrip baru',
        ])->assertSessionHas('error');

        Bus::assertNothingDispatched();
    }

    public function test_processed_meeting_cannot_be_processed_again(): void
    {
        Bus::fake();
        Storage::fake('public');
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $meeting = $this->meeting($unit, $user, ['status' => 'Selesai Diproses', 'transcript' => 'lama']);

        $this->actingAs($user)->post(route('meetings.process', $meeting), [
            'type' => 'text',
            'text_input' => 'Transkrip baru',
        ])->assertSessionHas('error');

        Bus::assertNothingDispatched();
        $this->assertSame('Selesai Diproses', $meeting->fresh()->status);
    }

    public function test_failed_meeting_can_be_processed_again(): void
    {
        Bus::fake();
        Storage::fake('public');
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $meeting = $this->meeting($unit, $user, ['status' => 'Gagal']);

        $this->actingAs($user)->post(route('meetings.process', $meeting), [
            'type' => 'text',
            'text_input' => 'Transkrip baru',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Memproses', $meeting->fresh()->status);
    }

    public function test_processed_meeting_details_cannot_be_updated(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $meeting = $this->meeting($unit, $user, ['status' => 'Selesai Diproses', 'title' => 'Judul asli']);

        $this->actingAs($user)->put(route('meetings.update', $meeting), [
            'title' => 'Judul diubah',
            'date' => now()->toDateTimeString(),
        ])->assertSessionHas('error');

        $this->assertSame('Judul asli', $meeting->fresh()->title);
    }

    public function test_deleting_meeting_removes_source_file_and_forum_attachments(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create();
        $admin = $this->userInUnit($unit, 'admin');
        $meeting = $this->meeting($unit, $admin, ['source_file_path' => 'audio_uploads/rekaman.mp3']);
        Storage::disk('public')->put('audio_uploads/rekaman.mp3', 'audio');

        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $admin->id, 'body' => 'Halo']);
        $reply = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $admin->id, 'parent_id' => $comment->id, 'body' => 'Balas']);
        $comment->attachments()->create(['file_path' => 'forum_attachments/a.pdf', 'file_name' => 'a.pdf', 'file_size' => 1, 'mime_type' => 'application/pdf']);
        $reply->attachments()->create(['file_path' => 'forum_attachments/b.pdf', 'file_name' => 'b.pdf', 'file_size' => 1, 'mime_type' => 'application/pdf']);
        Storage::disk('public')->put('forum_attachments/a.pdf', 'a');
        Storage::disk('public')->put('forum_attachments/b.pdf', 'b');

        $this->actingAs($admin)->delete(route('meetings.destroy', $meeting))->assertRedirect();

        $this->assertModelMissing($meeting);
        Storage::disk('public')->assertMissing('audio_uploads/rekaman.mp3');
        Storage::disk('public')->assertMissing('forum_attachments/a.pdf');
        Storage::disk('public')->assertMissing('forum_attachments/b.pdf');
    }

    public function test_stt_test_endpoint_requires_authentication(): void
    {
        Storage::fake('public');

        $this->post('/stt/test', ['file' => UploadedFile::fake()->create('a.mp3', 10, 'audio/mpeg')])
            ->assertRedirect(route('login'));

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}
