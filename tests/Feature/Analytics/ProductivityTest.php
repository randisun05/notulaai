<?php

namespace Tests\Feature\Analytics;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_productivity_ranks_users_by_completed_tasks_descending(): void
    {
        $unit = Unit::factory()->create();
        $viewer = User::factory()->create(['unit_id' => $unit->id]);
        $viewer->syncRoles(['user']);
        $topPerformer = User::factory()->create(['unit_id' => $unit->id, 'name' => 'Rajin Sekali']);
        $lowPerformer = User::factory()->create(['unit_id' => $unit->id, 'name' => 'Biasa Saja']);

        Task::create(['unit_id' => $unit->id, 'assignee_id' => $topPerformer->id, 'title' => 'A', 'status' => 'Done']);
        Task::create(['unit_id' => $unit->id, 'assignee_id' => $topPerformer->id, 'title' => 'B', 'status' => 'Done']);
        Task::create(['unit_id' => $unit->id, 'assignee_id' => $lowPerformer->id, 'title' => 'C', 'status' => 'Done']);
        Task::create(['unit_id' => $unit->id, 'assignee_id' => $lowPerformer->id, 'title' => 'D', 'status' => 'Todo']);

        $response = $this->actingAs($viewer)->get(route('analytics.productivity'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Analytics/Productivity')
            ->where('productivity.0.name', 'Rajin Sekali')
            ->where('productivity.0.completed_count', 2)
            ->where('productivity.1.completion_rate', 50)
        );
    }

    public function test_productivity_excludes_users_from_other_units_for_non_superadmin(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $viewer = User::factory()->create(['unit_id' => $unit->id]);
        $viewer->syncRoles(['user']);
        $outsider = User::factory()->create(['unit_id' => $otherUnit->id, 'name' => 'Orang Unit Lain']);
        Task::create(['unit_id' => $otherUnit->id, 'assignee_id' => $outsider->id, 'title' => 'X', 'status' => 'Done']);

        $response = $this->actingAs($viewer)->get(route('analytics.productivity'));

        $response->assertInertia(fn ($page) => $page->where('productivity', []));
    }

    public function test_users_without_any_tasks_are_excluded_from_ranking(): void
    {
        $unit = Unit::factory()->create();
        $viewer = User::factory()->create(['unit_id' => $unit->id]);
        $viewer->syncRoles(['user']);
        User::factory()->create(['unit_id' => $unit->id, 'name' => 'Tidak Punya Task']);

        $response = $this->actingAs($viewer)->get(route('analytics.productivity'));

        $response->assertInertia(fn ($page) => $page->where('productivity', []));
    }
}
