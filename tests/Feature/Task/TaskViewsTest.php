<?php

namespace Tests\Feature\Task;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskViewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_view_task_detail_within_their_unit(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'Todo']);

        $response = $this->actingAs($user)->get(route('tasks.show', $task));

        $response->assertOk();
    }

    public function test_user_cannot_view_task_from_another_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $otherUnit->id, 'title' => 'Task unit lain', 'status' => 'Todo']);

        $response = $this->actingAs($user)->get(route('tasks.show', $task));

        $response->assertForbidden();
    }

    public function test_kanban_board_groups_tasks_by_status(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        Task::create(['unit_id' => $unit->id, 'title' => 'Task 1', 'status' => 'Todo']);
        Task::create(['unit_id' => $unit->id, 'title' => 'Task 2', 'status' => 'Done']);

        $response = $this->actingAs($user)->get(route('tasks.kanban'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tasks/Kanban')
            ->has('tasksByStatus.Todo', 1)
            ->has('tasksByStatus.Done', 1)
            ->has('tasksByStatus.Cancelled', 0)
        );
    }

    public function test_status_change_is_recorded_on_task_own_activity_timeline(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'Todo']);

        $this->actingAs($user)->patch(route('tasks.update-status', $task), ['status' => 'In Progress']);

        $this->assertDatabaseHas('activities', [
            'task_id' => $task->id,
            'type' => 'task.status_changed',
        ]);
    }
}
