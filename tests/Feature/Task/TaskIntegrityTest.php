<?php

namespace Tests\Feature\Task;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userInUnit(Unit $unit, string $role = 'user'): User
    {
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_admin_cannot_approve_a_task_assigned_to_themselves(): void
    {
        $unit = Unit::factory()->create();
        $admin = $this->userInUnit($unit, 'admin');
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $admin->id, 'title' => 'Tugas sendiri', 'status' => 'Review']);

        $this->actingAs($admin)->post(route('tasks.approve', $task))->assertForbidden();

        $this->assertSame('Review', $task->fresh()->status);
    }

    public function test_admin_cannot_reject_a_task_assigned_to_themselves(): void
    {
        $unit = Unit::factory()->create();
        $admin = $this->userInUnit($unit, 'admin');
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $admin->id, 'title' => 'Tugas sendiri', 'status' => 'Review']);

        $this->actingAs($admin)->post(route('tasks.reject', $task))->assertForbidden();
    }

    public function test_another_admin_can_approve_a_task_assigned_to_an_admin(): void
    {
        $unit = Unit::factory()->create();
        $assignee = $this->userInUnit($unit, 'admin');
        $approver = $this->userInUnit($unit, 'admin');
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $assignee->id, 'title' => 'Tugas', 'status' => 'Review']);

        $this->actingAs($approver)->post(route('tasks.approve', $task))->assertRedirect();

        $this->assertSame('Done', $task->fresh()->status);
    }

    public function test_task_page_hides_approve_button_from_the_assignee_admin(): void
    {
        $unit = Unit::factory()->create();
        $admin = $this->userInUnit($unit, 'admin');
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $admin->id, 'title' => 'Tugas sendiri', 'status' => 'Review']);

        $this->actingAs($admin)->get(route('tasks.show', $task))
            ->assertInertia(fn ($page) => $page->where('canApprove', false));
    }

    public function test_submitting_sla_form_without_a_value_clears_the_sla(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Tugas', 'status' => 'Todo', 'sla_hours' => 24]);

        $this->actingAs($user)->patch(route('tasks.update-sla', $task), [])->assertRedirect();

        $this->assertNull($task->fresh()->sla_hours);
    }
}
