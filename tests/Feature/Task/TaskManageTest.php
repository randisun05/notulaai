<?php

namespace Tests\Feature\Task;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_regular_user_cannot_access_create_form_or_store(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $this->actingAs($user)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($user)->post(route('tasks.store'), ['title' => 'X', 'priority' => 'Medium'])->assertForbidden();
    }

    public function test_admin_can_create_task_manually_for_own_unit(): void
    {
        $unit = Unit::factory()->create();
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        $assignee = User::factory()->create(['unit_id' => $unit->id]);

        $response = $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'Task manual',
            'description' => 'Deskripsi task',
            'assignee_id' => $assignee->id,
            'priority' => 'High',
            'deadline' => now()->addDays(3)->toDateString(),
        ]);

        $task = Task::first();
        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame('Task manual', $task->title);
        $this->assertSame($unit->id, $task->unit_id);
        $this->assertSame($assignee->id, $task->assignee_id);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'type' => 'task.created']);
    }

    public function test_admin_cannot_assign_task_to_user_outside_their_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        $outsider = User::factory()->create(['unit_id' => $otherUnit->id]);

        $response = $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'Task manual',
            'assignee_id' => $outsider->id,
            'priority' => 'Medium',
        ]);

        $response->assertSessionHasErrors('assignee_id');
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_superadmin_can_create_task_for_any_unit(): void
    {
        $adminUnit = Unit::factory()->create();
        $targetUnit = Unit::factory()->create();
        $superadmin = User::factory()->create(['unit_id' => $adminUnit->id]);
        $superadmin->syncRoles(['superadmin']);

        $response = $this->actingAs($superadmin)->post(route('tasks.store'), [
            'title' => 'Task lintas unit',
            'unit_id' => $targetUnit->id,
            'priority' => 'Low',
        ]);

        $task = Task::first();
        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame($targetUnit->id, $task->unit_id);
    }

    public function test_admin_can_edit_task_details_in_own_unit(): void
    {
        $unit = Unit::factory()->create();
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Judul lama', 'priority' => 'Low', 'status' => 'Todo']);

        $response = $this->actingAs($admin)->put(route('tasks.update', $task), [
            'title' => 'Judul baru',
            'priority' => 'Urgent',
        ]);

        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame('Judul baru', $task->fresh()->title);
        $this->assertSame('Urgent', $task->fresh()->priority);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'type' => 'task.details_updated']);
    }

    public function test_admin_cannot_edit_task_from_another_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        $task = Task::create(['unit_id' => $otherUnit->id, 'title' => 'Task unit lain', 'priority' => 'Low', 'status' => 'Todo']);

        $this->actingAs($admin)->get(route('tasks.edit', $task))->assertForbidden();
        $this->actingAs($admin)->put(route('tasks.update', $task), ['title' => 'Hacked', 'priority' => 'Low'])->assertForbidden();
        $this->assertSame('Task unit lain', $task->fresh()->title);
    }

    public function test_regular_user_cannot_edit_task_details(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task saya', 'priority' => 'Low', 'status' => 'Todo']);

        $this->actingAs($user)->get(route('tasks.edit', $task))->assertForbidden();
    }

    public function test_index_page_exposes_can_manage_flag(): void
    {
        $unit = Unit::factory()->create();
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $this->actingAs($admin)->get(route('tasks.index'))
            ->assertInertia(fn ($page) => $page->where('canManage', true));

        $this->actingAs($user)->get(route('tasks.index'))
            ->assertInertia(fn ($page) => $page->where('canManage', false));
    }
}
