<?php

namespace Tests\Feature\Task;

use App\Jobs\EscalateOverdueTasks;
use App\Jobs\SendTaskDeadlineReminders;
use App\Mail\TaskDeadlineReminder;
use App\Mail\TaskEscalationNotice;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TaskEscalationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_escalation_job_emails_creator_and_marks_task_escalated(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $creator = User::factory()->create(['unit_id' => $unit->id]);
        $task = Task::create([
            'unit_id' => $unit->id,
            'created_by' => $creator->id,
            'title' => 'Task terlambat',
            'status' => 'Todo',
            'deadline' => now()->subDays(3)->toDateString(),
        ]);

        (new EscalateOverdueTasks())->handle(app(\App\Services\Meeting\ActivityLogger::class));

        $task->refresh();
        $this->assertNotNull($task->escalated_at);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'type' => 'task.escalated']);
        Mail::assertQueued(TaskEscalationNotice::class, fn ($mail) => $mail->hasTo($creator->email));
    }

    public function test_escalation_job_does_not_re_escalate_already_escalated_task(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $creator = User::factory()->create(['unit_id' => $unit->id]);
        $task = Task::create([
            'unit_id' => $unit->id,
            'created_by' => $creator->id,
            'title' => 'Task sudah dieskalasi',
            'status' => 'Todo',
            'deadline' => now()->subDays(3)->toDateString(),
            'escalated_at' => now()->subDay(),
        ]);

        (new EscalateOverdueTasks())->handle(app(\App\Services\Meeting\ActivityLogger::class));

        Mail::assertNothingQueued();
        $this->assertDatabaseMissing('activities', ['task_id' => $task->id, 'type' => 'task.escalated']);
    }

    public function test_escalation_job_skips_tasks_that_are_not_overdue_or_breached(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $creator = User::factory()->create(['unit_id' => $unit->id]);
        Task::create([
            'unit_id' => $unit->id,
            'created_by' => $creator->id,
            'title' => 'Task masih aman',
            'status' => 'Todo',
            'deadline' => now()->addDays(5)->toDateString(),
        ]);

        (new EscalateOverdueTasks())->handle(app(\App\Services\Meeting\ActivityLogger::class));

        Mail::assertNothingQueued();
    }

    public function test_deadline_reminder_job_emails_assignee_for_task_due_tomorrow(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $assignee = User::factory()->create(['unit_id' => $unit->id]);
        Task::create([
            'unit_id' => $unit->id,
            'assignee_id' => $assignee->id,
            'title' => 'Task besok deadline',
            'status' => 'Todo',
            'deadline' => now()->addDay()->toDateString(),
        ]);

        (new SendTaskDeadlineReminders())->handle();

        Mail::assertQueued(TaskDeadlineReminder::class, fn ($mail) => $mail->hasTo($assignee->email));
    }

    public function test_deadline_reminder_job_ignores_tasks_not_due_tomorrow(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $assignee = User::factory()->create(['unit_id' => $unit->id]);
        Task::create([
            'unit_id' => $unit->id,
            'assignee_id' => $assignee->id,
            'title' => 'Task minggu depan',
            'status' => 'Todo',
            'deadline' => now()->addWeek()->toDateString(),
        ]);

        (new SendTaskDeadlineReminders())->handle();

        Mail::assertNothingQueued();
    }
}
