<?php

namespace Tests\Feature\Task;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_calendar_only_returns_tasks_within_requested_month(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task Agustus', 'status' => 'Todo', 'deadline' => '2026-08-15']);
        Task::create(['unit_id' => $unit->id, 'title' => 'Task September', 'status' => 'Todo', 'deadline' => '2026-09-05']);

        $response = $this->actingAs($user)->get(route('tasks.calendar', ['month' => 8, 'year' => 2026]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tasks/Calendar')
            ->has('tasksByDate.2026-08-15', 1)
            ->missing('tasksByDate.2026-09-05')
        );
    }

    public function test_calendar_only_shows_tasks_within_users_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task saya', 'status' => 'Todo', 'deadline' => '2026-08-10']);
        Task::create(['unit_id' => $otherUnit->id, 'title' => 'Task unit lain', 'status' => 'Todo', 'deadline' => '2026-08-10']);

        $response = $this->actingAs($user)->get(route('tasks.calendar', ['month' => 8, 'year' => 2026]));

        $response->assertInertia(fn ($page) => $page->has('tasksByDate.2026-08-10', 1));
    }
}
