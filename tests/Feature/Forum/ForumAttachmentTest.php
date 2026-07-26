<?php

namespace Tests\Feature\Forum;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ForumAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function createMeeting(User $user, Unit $unit): Meeting
    {
        return Meeting::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'title' => 'Rapat Uji Coba',
            'date' => now(),
            'agenda' => 'Agenda uji coba',
            'attendees' => 'Peserta uji coba',
            'status' => 'Dijadwalkan',
        ]);
    }

    public function test_comment_can_be_posted_with_attachments(): void
    {
        Storage::fake('public');

        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $response = $this->actingAs($user)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Lihat lampiran ini',
            'attachments' => [
                UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('foto.jpg'),
            ],
        ]);

        $response->assertRedirect();
        $comment = ForumComment::first();
        $this->assertCount(2, $comment->attachments);
        Storage::disk('public')->assertExists($comment->attachments->first()->file_path);
    }

    public function test_more_than_max_attachments_is_rejected(): void
    {
        Storage::fake('public');

        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $files = array_map(fn ($i) => UploadedFile::fake()->image("foto{$i}.jpg"), range(1, 6));

        $response = $this->actingAs($user)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Terlalu banyak lampiran',
            'attachments' => $files,
        ]);

        $response->assertSessionHasErrors('attachments');
        $this->assertDatabaseCount('forum_comments', 0);
    }

    public function test_deleting_comment_removes_attachment_files_from_disk(): void
    {
        Storage::fake('public');

        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $this->actingAs($user)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Akan dihapus',
            'attachments' => [UploadedFile::fake()->image('foto.jpg')],
        ]);

        $comment = ForumComment::first();
        $path = $comment->attachments->first()->file_path;
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)->delete(route('comments.destroy', $comment));

        Storage::disk('public')->assertMissing($path);
    }
}
