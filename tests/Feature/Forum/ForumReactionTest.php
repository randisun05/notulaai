<?php

namespace Tests\Feature\Forum;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumReactionTest extends TestCase
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

    public function test_user_can_react_to_a_comment(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);
        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'body' => 'Halo']);

        $response = $this->actingAs($user)->post(route('comments.reactions.toggle', $comment), ['emoji' => '👍']);

        $response->assertRedirect();
        $this->assertDatabaseHas('forum_comment_reactions', [
            'forum_comment_id' => $comment->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);
    }

    public function test_reacting_twice_with_same_emoji_removes_it(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);
        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'body' => 'Halo']);

        $this->actingAs($user)->post(route('comments.reactions.toggle', $comment), ['emoji' => '👍']);
        $this->actingAs($user)->post(route('comments.reactions.toggle', $comment), ['emoji' => '👍']);

        $this->assertDatabaseCount('forum_comment_reactions', 0);
    }

    public function test_unknown_emoji_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);
        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'body' => 'Halo']);

        $response = $this->actingAs($user)->post(route('comments.reactions.toggle', $comment), ['emoji' => '🐸']);

        $response->assertSessionHasErrors('emoji');
        $this->assertDatabaseCount('forum_comment_reactions', 0);
    }

    public function test_user_from_another_unit_cannot_react(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $meetingOwner = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider = User::factory()->create(['unit_id' => $ownUnit->id]);
        $outsider->syncRoles(['user']);
        $meeting = $this->createMeeting($meetingOwner, $otherUnit);
        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $meetingOwner->id, 'body' => 'Halo']);

        $response = $this->actingAs($outsider)->post(route('comments.reactions.toggle', $comment), ['emoji' => '👍']);

        $response->assertForbidden();
    }

    public function test_two_users_can_react_with_same_emoji_independently(): void
    {
        $unit = Unit::factory()->create();
        $userA = User::factory()->create(['unit_id' => $unit->id]);
        $userA->syncRoles(['user']);
        $userB = User::factory()->create(['unit_id' => $unit->id]);
        $userB->syncRoles(['user']);
        $meeting = $this->createMeeting($userA, $unit);
        $comment = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $userA->id, 'body' => 'Halo']);

        $this->actingAs($userA)->post(route('comments.reactions.toggle', $comment), ['emoji' => '👍']);
        $this->actingAs($userB)->post(route('comments.reactions.toggle', $comment), ['emoji' => '👍']);

        $this->assertDatabaseCount('forum_comment_reactions', 2);
    }
}
