<?php

namespace Tests\Feature\Forum;

use App\Mail\CommentMentionNotification;
use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForumMentionTest extends TestCase
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

    public function test_mentioning_a_unit_member_sends_notification_and_records_mention(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $author = User::factory()->create(['unit_id' => $unit->id]);
        $author->syncRoles(['user']);
        $mentioned = User::factory()->create(['unit_id' => $unit->id, 'name' => 'Budi Santoso']);
        $meeting = $this->createMeeting($author, $unit);

        $response = $this->actingAs($author)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Tolong cek ini @Budi Santoso',
        ]);

        $response->assertRedirect();
        $comment = ForumComment::first();
        $this->assertTrue($comment->mentionedUsers->contains($mentioned));
        Mail::assertQueued(CommentMentionNotification::class, function ($mail) use ($mentioned) {
            return $mail->hasTo($mentioned->email);
        });
    }

    public function test_mentioning_self_does_not_send_notification_to_self(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $author = User::factory()->create(['unit_id' => $unit->id, 'name' => 'Diri Sendiri']);
        $author->syncRoles(['user']);
        $meeting = $this->createMeeting($author, $unit);

        $this->actingAs($author)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Catatan untuk @Diri Sendiri',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_mentioning_user_from_another_unit_is_not_matched(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $author = User::factory()->create(['unit_id' => $unit->id]);
        $author->syncRoles(['user']);
        $outsider = User::factory()->create(['unit_id' => $otherUnit->id, 'name' => 'Orang Lain Unit']);
        $meeting = $this->createMeeting($author, $unit);

        $this->actingAs($author)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Halo @Orang Lain Unit',
        ]);

        $comment = ForumComment::first();
        $this->assertFalse($comment->mentionedUsers->contains($outsider));
        Mail::assertNothingQueued();
    }

    public function test_editing_comment_without_changing_mention_does_not_resend_notification(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $author = User::factory()->create(['unit_id' => $unit->id]);
        $author->syncRoles(['user']);
        $mentioned = User::factory()->create(['unit_id' => $unit->id, 'name' => 'Budi Santoso']);
        $meeting = $this->createMeeting($author, $unit);

        $this->actingAs($author)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Cek ini @Budi Santoso',
        ]);
        $comment = ForumComment::first();

        Mail::assertQueued(CommentMentionNotification::class, 1);

        $this->actingAs($author)->put(route('comments.update', $comment), [
            'body' => 'Cek ini @Budi Santoso ya, penting',
        ]);

        Mail::assertQueued(CommentMentionNotification::class, 1);
    }
}
