<?php

namespace Tests\Feature\Task;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskSlaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_task_with_past_deadline_is_marked_overdue(): void
    {
        $unit = Unit::factory()->create();
        $task = Task::create([
            'unit_id' => $unit->id,
            'title' => 'Task lewat deadline',
            'status' => 'Todo',
            'deadline' => now()->subDays(2)->toDateString(),
        ]);

        $this->assertTrue($task->fresh()->is_overdue);
    }

    public function test_task_due_today_is_not_yet_overdue(): void
    {
        $unit = Unit::factory()->create();
        $task = Task::create([
            'unit_id' => $unit->id,
            'title' => 'Task hari ini',
            'status' => 'Todo',
            'deadline' => now()->toDateString(),
        ]);

        $this->assertFalse($task->fresh()->is_overdue);
    }

    public function test_done_task_is_never_overdue_even_with_past_deadline(): void
    {
        $unit = Unit::factory()->create();
        $task = Task::create([
            'unit_id' => $unit->id,
            'title' => 'Task selesai',
            'status' => 'Done',
            'deadline' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertFalse($task->fresh()->is_overdue);
    }

    public function test_task_breaches_sla_after_configured_hours(): void
    {
        $unit = Unit::factory()->create();
        $task = Task::create([
            'unit_id' => $unit->id,
            'title' => 'Task SLA',
            'status' => 'Todo',
            'sla_hours' => 1,
        ]);
        $task->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->assertTrue($task->fresh()->is_sla_breached);
    }

    public function test_task_without_sla_hours_never_breaches_sla(): void
    {
        $unit = Unit::factory()->create();
        $task = Task::create([
            'unit_id' => $unit->id,
            'title' => 'Task tanpa SLA',
            'status' => 'Todo',
        ]);
        $task->forceFill(['created_at' => now()->subDays(30)])->save();

        $this->assertFalse($task->fresh()->is_sla_breached);
    }

    public function test_assignee_can_update_sla(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task uji coba', 'status' => 'Todo']);

        $response = $this->actingAs($user)->patch(route('tasks.update-sla', $task), ['sla_hours' => 48]);

        $response->assertRedirect();
        $this->assertSame(48, $task->fresh()->sla_hours);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'type' => 'task.sla_updated']);
    }
}
