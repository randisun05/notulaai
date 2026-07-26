<?php

namespace Tests\Feature\Task;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskGeneratorTest extends TestCase
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
            'status' => 'Selesai Diproses',
        ]);
    }

    public function test_converting_action_item_creates_task_and_matches_assignee_by_name(): void
    {
        $unit = Unit::factory()->create();
        $creator = User::factory()->create(['unit_id' => $unit->id]);
        $creator->syncRoles(['user']);
        $assignee = User::factory()->create(['unit_id' => $unit->id, 'name' => 'Budi Santoso']);

        $meeting = $this->createMeeting($creator, $unit);
        $actionItem = MeetingActionItem::create([
            'meeting_id' => $meeting->id,
            'title' => 'Kirim laporan ke klien',
            'assignee_name' => 'Budi Santoso',
            'deadline' => '2026-08-05',
        ]);

        $response = $this->actingAs($creator)->post(route('meetings.action-items.convert', [
            'meeting' => $meeting->id,
            'actionItem' => $actionItem->id,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseCount('tasks', 1);

        $task = Task::first();
        $this->assertSame('Kirim laporan ke klien', $task->title);
        $this->assertSame($assignee->id, $task->assignee_id);
        $this->assertSame('Todo', $task->status);
        $this->assertTrue($actionItem->fresh()->converted_to_task);
    }

    public function test_action_item_cannot_be_converted_twice(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $meeting = $this->createMeeting($user, $unit);
        $actionItem = MeetingActionItem::create([
            'meeting_id' => $meeting->id,
            'title' => 'Sudah dikonversi',
            'converted_to_task' => true,
        ]);

        $this->actingAs($user)->post(route('meetings.action-items.convert', [
            'meeting' => $meeting->id,
            'actionItem' => $actionItem->id,
        ]));

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_user_from_another_unit_cannot_convert_action_item(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();

        $meetingOwner = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider = User::factory()->create(['unit_id' => $ownUnit->id]);
        $outsider->syncRoles(['user']);

        $meeting = $this->createMeeting($meetingOwner, $otherUnit);
        $actionItem = MeetingActionItem::create([
            'meeting_id' => $meeting->id,
            'title' => 'Task lintas unit',
        ]);

        $response = $this->actingAs($outsider)->post(route('meetings.action-items.convert', [
            'meeting' => $meeting->id,
            'actionItem' => $actionItem->id,
        ]));

        $response->assertForbidden();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_assignee_can_update_own_task_status(): void
    {
        $unit = Unit::factory()->create();
        $assignee = User::factory()->create(['unit_id' => $unit->id]);
        $assignee->syncRoles(['user']);

        $task = Task::create([
            'unit_id' => $unit->id,
            'assignee_id' => $assignee->id,
            'title' => 'Task saya',
            'status' => 'Todo',
        ]);

        $response = $this->actingAs($assignee)->patch(route('tasks.update-status', $task->id), [
            'status' => 'In Progress',
        ]);

        $response->assertRedirect();
        $this->assertSame('In Progress', $task->fresh()->status);
    }

    public function test_user_outside_unit_and_not_assignee_cannot_update_task_status(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $outsider = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider->syncRoles(['user']);

        $task = Task::create([
            'unit_id' => $unit->id,
            'title' => 'Task unit lain',
            'status' => 'Todo',
        ]);

        $response = $this->actingAs($outsider)->patch(route('tasks.update-status', $task->id), [
            'status' => 'Done',
        ]);

        $response->assertForbidden();
        $this->assertSame('Todo', $task->fresh()->status);
    }
}
