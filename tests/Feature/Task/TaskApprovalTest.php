<?php

namespace Tests\Feature\Task;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_regular_user_cannot_move_task_directly_to_done(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task uji coba', 'status' => 'In Progress']);

        $response = $this->actingAs($user)->patch(route('tasks.update-status', $task), ['status' => 'Done']);

        $response->assertSessionHasErrors('status');
        $this->assertSame('In Progress', $task->fresh()->status);
    }

    public function test_assignee_can_submit_task_for_review(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task uji coba', 'status' => 'In Progress']);

        $response = $this->actingAs($user)->patch(route('tasks.update-status', $task), ['status' => 'Review']);

        $response->assertRedirect();
        $this->assertSame('Review', $task->fresh()->status);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'type' => 'task.approval_requested']);
    }

    public function test_admin_can_approve_task_in_review(): void
    {
        $unit = Unit::factory()->create();
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'Review']);

        $response = $this->actingAs($admin)->post(route('tasks.approve', $task));

        $response->assertRedirect();
        $this->assertSame('Done', $task->fresh()->status);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'type' => 'task.approved']);
    }

    public function test_regular_user_cannot_approve_task(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'Review']);

        $response = $this->actingAs($user)->post(route('tasks.approve', $task));

        $response->assertForbidden();
        $this->assertSame('Review', $task->fresh()->status);
    }

    public function test_admin_can_reject_task_back_to_in_progress(): void
    {
        $unit = Unit::factory()->create();
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'Review']);

        $response = $this->actingAs($admin)->post(route('tasks.reject', $task), ['reason' => 'Belum lengkap']);

        $response->assertRedirect();
        $this->assertSame('In Progress', $task->fresh()->status);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'type' => 'task.rejected']);
    }

    public function test_cannot_approve_task_that_is_not_in_review(): void
    {
        $unit = Unit::factory()->create();
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'Todo']);

        $response = $this->actingAs($admin)->post(route('tasks.approve', $task));

        $response->assertRedirect();
        $this->assertSame('Todo', $task->fresh()->status);
    }
}
