<?php

namespace Tests\Feature\Forum;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumReplyNestingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_to_a_reply_is_attached_to_the_top_level_comment(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = Meeting::create(['title' => 'Rapat', 'date' => now(), 'status' => 'Dijadwalkan', 'unit_id' => $unit->id, 'user_id' => $user->id]);
        $root = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'body' => 'Utama']);
        $reply = ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'parent_id' => $root->id, 'body' => 'Balasan']);

        // Forum hanya menampilkan satu tingkat balasan; balasan atas balasan
        // yang disimpan di bawah $reply tidak akan pernah terlihat.
        $this->actingAs($user)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Balasan atas balasan',
            'parent_id' => $reply->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('forum_comments', ['body' => 'Balasan atas balasan', 'parent_id' => $root->id]);
    }
}
