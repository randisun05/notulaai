<?php

namespace Tests\Feature\Meeting;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ActivityTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
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

    public function test_scheduling_a_meeting_logs_an_activity(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $this->actingAs($user)->post(route('meetings.store'), [
            'title' => 'Rapat Baru',
            'date' => now()->toDateTimeString(),
            'agenda' => 'Agenda',
            'attendees' => 'Semua',
        ]);

        $meeting = Meeting::first();
        $this->assertDatabaseHas('activities', [
            'meeting_id' => $meeting->id,
            'type' => 'meeting.created',
        ]);
    }

    public function test_posting_a_comment_logs_an_activity(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $this->actingAs($user)->post(route('meetings.comments.store', $meeting), [
            'body' => 'Komentar pertama',
        ]);

        $this->assertDatabaseHas('activities', [
            'meeting_id' => $meeting->id,
            'type' => 'comment.created',
        ]);
    }

    public function test_converting_action_item_to_task_logs_an_activity(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);
        $actionItem = MeetingActionItem::create(['meeting_id' => $meeting->id, 'title' => 'Kirim laporan']);

        $this->actingAs($user)->post(route('meetings.action-items.convert', [
            'meeting' => $meeting->id,
            'actionItem' => $actionItem->id,
        ]));

        $this->assertDatabaseHas('activities', [
            'meeting_id' => $meeting->id,
            'type' => 'task.created',
        ]);
    }

    public function test_changing_task_status_logs_an_activity(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);
        $task = Task::create([
            'meeting_id' => $meeting->id,
            'unit_id' => $unit->id,
            'title' => 'Task uji coba',
            'status' => 'Todo',
        ]);

        $this->actingAs($user)->patch(route('tasks.update-status', $task), ['status' => 'In Progress']);

        $this->assertDatabaseHas('activities', [
            'meeting_id' => $meeting->id,
            'type' => 'task.status_changed',
        ]);
    }
}
