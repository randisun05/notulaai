<?php

namespace Tests\Feature\Forum;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumCommentTest extends TestCase
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

    public function test_user_can_post_comment_on_meeting_they_can_view(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $response = $this->actingAs($user)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Komentar pertama',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('forum_comments', [
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
            'body' => 'Komentar pertama',
            'parent_id' => null,
        ]);
    }

    public function test_user_outside_unit_cannot_comment(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $meetingOwner = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider = User::factory()->create(['unit_id' => $ownUnit->id]);
        $outsider->syncRoles(['user']);

        $meeting = $this->createMeeting($meetingOwner, $otherUnit);

        $response = $this->actingAs($outsider)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Tidak boleh komentar',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('forum_comments', 0);
    }

    public function test_user_can_reply_to_a_comment(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $parent = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'body' => 'Induk']);

        $response = $this->actingAs($user)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Balasan',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('forum_comments', [
            'parent_id' => $parent->id,
            'body' => 'Balasan',
        ]);
    }

    public function test_reply_cannot_be_attached_to_comment_from_another_meeting(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meetingA = $this->createMeeting($user, $unit);
        $meetingB = $this->createMeeting($user, $unit);

        $commentOnA = ForumComment::create(['meeting_id' => $meetingA->id, 'user_id' => $user->id, 'body' => 'Komentar A']);

        $response = $this->actingAs($user)->post(route('meetings.comments.store', $meetingB), [
            'body' => 'Mencoba membalas lintas rapat',
            'parent_id' => $commentOnA->id,
        ]);

        $response->assertNotFound();
    }

    public function test_author_can_update_own_comment(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);
        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'body' => 'Asli']);

        $response = $this->actingAs($user)->put(route('comments.update', $comment), ['body' => 'Sudah diedit']);

        $response->assertRedirect();
        $this->assertSame('Sudah diedit', $comment->fresh()->body);
    }

    public function test_other_user_cannot_update_someone_elses_comment(): void
    {
        $unit = Unit::factory()->create();
        $author = User::factory()->create(['unit_id' => $unit->id]);
        $other = User::factory()->create(['unit_id' => $unit->id]);
        $other->syncRoles(['user']);
        $meeting = $this->createMeeting($author, $unit);
        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $author->id, 'body' => 'Asli']);

        $response = $this->actingAs($other)->put(route('comments.update', $comment), ['body' => 'Coba diedit orang lain']);

        $response->assertForbidden();
        $this->assertSame('Asli', $comment->fresh()->body);
    }

    public function test_author_can_delete_own_comment(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);
        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'body' => 'Akan dihapus']);

        $response = $this->actingAs($user)->delete(route('comments.destroy', $comment));

        $response->assertRedirect();
        $this->assertDatabaseMissing('forum_comments', ['id' => $comment->id]);
    }

    public function test_superadmin_can_delete_anyones_comment(): void
    {
        $unit = Unit::factory()->create();
        $author = User::factory()->create(['unit_id' => $unit->id]);
        $superadmin = User::factory()->create();
        $superadmin->syncRoles(['superadmin']);
        $meeting = $this->createMeeting($author, $unit);
        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $author->id, 'body' => 'Akan dihapus admin']);

        $response = $this->actingAs($superadmin)->delete(route('comments.destroy', $comment));

        $response->assertRedirect();
        $this->assertDatabaseMissing('forum_comments', ['id' => $comment->id]);
    }
}
