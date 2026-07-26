<?php

namespace Tests\Feature\Task;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_generic_status_dropdown_cannot_be_used_to_reach_review(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task uji coba', 'status' => 'In Progress']);

        $response = $this->actingAs($user)->patch(route('tasks.update-status', $task), ['status' => 'Review']);

        $response->assertSessionHasErrors('status');
        $this->assertSame('In Progress', $task->fresh()->status);
    }

    public function test_submitting_for_review_without_note_or_attachment_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task uji coba', 'status' => 'In Progress']);

        $response = $this->actingAs($user)->post(route('tasks.submit-for-review', $task));

        $response->assertSessionHasErrors('note');
        $this->assertSame('In Progress', $task->fresh()->status);
        $this->assertDatabaseCount('task_evidences', 0);
    }

    public function test_submitting_for_review_with_note_only_succeeds(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task uji coba', 'status' => 'In Progress']);

        $response = $this->actingAs($user)->post(route('tasks.submit-for-review', $task), [
            'note' => 'Sudah selesai dikerjakan, tinggal direview.',
        ]);

        $response->assertRedirect();
        $this->assertSame('Review', $task->fresh()->status);
        $this->assertDatabaseHas('task_evidences', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'note' => 'Sudah selesai dikerjakan, tinggal direview.',
        ]);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'type' => 'task.approval_requested']);
    }

    public function test_submitting_for_review_with_attachments_stores_files(): void
    {
        Storage::fake('public');

        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task uji coba', 'status' => 'In Progress']);

        $response = $this->actingAs($user)->post(route('tasks.submit-for-review', $task), [
            'attachments' => [
                UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('bukti.jpg'),
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame('Review', $task->fresh()->status);
        $evidence = $task->evidences()->first();
        $this->assertCount(2, $evidence->attachments);
        Storage::disk('public')->assertExists($evidence->attachments->first()->file_path);
    }

    public function test_cannot_submit_for_review_when_task_already_done_or_cancelled(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task uji coba', 'status' => 'Cancelled']);

        $response = $this->actingAs($user)->post(route('tasks.submit-for-review', $task), ['note' => 'Catatan']);

        $response->assertSessionHas('error');
        $this->assertSame('Cancelled', $task->fresh()->status);
        $this->assertDatabaseCount('task_evidences', 0);
    }

    public function test_cannot_submit_for_review_twice_in_a_row(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Task uji coba', 'status' => 'Review']);

        $response = $this->actingAs($user)->post(route('tasks.submit-for-review', $task), ['note' => 'Catatan lagi']);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('task_evidences', 0);
    }

    public function test_user_from_another_unit_cannot_submit_for_review(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $outsider = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider->syncRoles(['user']);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'In Progress']);

        $response = $this->actingAs($outsider)->post(route('tasks.submit-for-review', $task), ['note' => 'Catatan']);

        $response->assertForbidden();
    }

    public function test_guest_cannot_submit_for_review(): void
    {
        $unit = Unit::factory()->create();
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji coba', 'status' => 'In Progress']);

        $this->post(route('tasks.submit-for-review', $task), ['note' => 'Catatan'])->assertRedirect(route('login'));
    }

    public function test_task_detail_page_shows_evidence_to_approver(): void
    {
        $unit = Unit::factory()->create();
        $assignee = User::factory()->create(['unit_id' => $unit->id]);
        $assignee->syncRoles(['user']);
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $assignee->id, 'title' => 'Task uji coba', 'status' => 'In Progress']);

        $this->actingAs($assignee)->post(route('tasks.submit-for-review', $task), ['note' => 'Sudah kelar']);

        $response = $this->actingAs($admin)->get(route('tasks.show', $task));

        $response->assertInertia(fn ($page) => $page
            ->component('Tasks/Show')
            ->has('task.evidences', 1)
            ->where('task.evidences.0.note', 'Sudah kelar')
        );
    }
}
