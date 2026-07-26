<?php

namespace Tests\Feature\Task;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDispositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_disposing_a_task_reassigns_it_and_records_history(): void
    {
        $unit = Unit::factory()->create();
        $fromUser = User::factory()->create(['unit_id' => $unit->id]);
        $fromUser->syncRoles(['user']);
        $toUser = User::factory()->create(['unit_id' => $unit->id, 'name' => 'Penerima Disposisi']);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'Todo']);

        $response = $this->actingAs($fromUser)->post(route('tasks.dispositions.store', $task), [
            'to_user_id' => $toUser->id,
            'note' => 'Tolong segera ditindaklanjuti',
        ]);

        $response->assertRedirect();
        $task->refresh();
        $this->assertSame($toUser->id, $task->assignee_id);
        $this->assertSame('Penerima Disposisi', $task->assignee_name);

        $this->assertDatabaseHas('task_dispositions', [
            'task_id' => $task->id,
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'note' => 'Tolong segera ditindaklanjuti',
        ]);
        $this->assertDatabaseHas('activities', [
            'task_id' => $task->id,
            'type' => 'task.disposed',
        ]);
    }

    public function test_cannot_dispose_task_to_user_outside_task_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $fromUser = User::factory()->create(['unit_id' => $unit->id]);
        $fromUser->syncRoles(['user']);
        $outsider = User::factory()->create(['unit_id' => $otherUnit->id]);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'Todo']);

        $response = $this->actingAs($fromUser)->post(route('tasks.dispositions.store', $task), [
            'to_user_id' => $outsider->id,
        ]);

        $response->assertSessionHasErrors('to_user_id');
        $this->assertDatabaseCount('task_dispositions', 0);
    }

    public function test_user_from_another_unit_cannot_dispose_task(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $outsider = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider->syncRoles(['user']);
        $toUser = User::factory()->create(['unit_id' => $unit->id]);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'Todo']);

        $response = $this->actingAs($outsider)->post(route('tasks.dispositions.store', $task), [
            'to_user_id' => $toUser->id,
        ]);

        $response->assertForbidden();
    }
}
