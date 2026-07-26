<?php

namespace Tests\Feature\Analytics;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatmapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_heatmap_counts_tasks_created_today(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task 1', 'status' => 'Todo']);
        Task::create(['unit_id' => $unit->id, 'title' => 'Task 2', 'status' => 'Todo']);

        $response = $this->actingAs($user)->get(route('analytics.heatmap'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Analytics/Heatmap')
            ->where('maxCount', 2)
        );
    }

    public function test_heatmap_excludes_tasks_from_other_units(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $otherUnit->id, 'title' => 'Task unit lain', 'status' => 'Todo']);

        $response = $this->actingAs($user)->get(route('analytics.heatmap'));

        $response->assertInertia(fn ($page) => $page->where('maxCount', 0));
    }

    public function test_heatmap_spans_twelve_weeks(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $response = $this->actingAs($user)->get(route('analytics.heatmap'));

        $response->assertInertia(fn ($page) => $page->has('weeks', 12));
    }
}
