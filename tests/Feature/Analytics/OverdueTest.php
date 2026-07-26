<?php

namespace Tests\Feature\Analytics;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_overdue_analysis_lists_only_overdue_open_tasks(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Overdue', 'status' => 'Todo', 'deadline' => now()->subDays(10)->toDateString()]);
        Task::create(['unit_id' => $unit->id, 'title' => 'Done tapi lewat deadline', 'status' => 'Done', 'deadline' => now()->subDays(10)->toDateString()]);
        Task::create(['unit_id' => $unit->id, 'title' => 'Belum jatuh tempo', 'status' => 'Todo', 'deadline' => now()->addDays(5)->toDateString()]);

        $response = $this->actingAs($user)->get(route('analytics.overdue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Analytics/Overdue')
            ->has('overdueTasks', 1)
            ->where('overdueTasks.0.title', 'Overdue')
        );
    }

    public function test_age_buckets_categorize_by_days_overdue(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Baru overdue', 'status' => 'Todo', 'deadline' => now()->subDays(2)->toDateString()]);
        Task::create(['unit_id' => $unit->id, 'title' => 'Lama overdue', 'status' => 'Todo', 'deadline' => now()->subDays(45)->toDateString()]);

        $response = $this->actingAs($user)->get(route('analytics.overdue'));

        $response->assertInertia(fn ($page) => $page
            ->where('ageBuckets.0.label', '1-3 hari')
            ->where('ageBuckets.0.count', 1)
            ->where('ageBuckets.3.label', '> 30 hari')
            ->where('ageBuckets.3.count', 1)
        );
    }

    public function test_non_superadmin_only_sees_own_unit_overdue_tasks(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $otherUnit->id, 'title' => 'Unit lain', 'status' => 'Todo', 'deadline' => now()->subDays(5)->toDateString()]);

        $response = $this->actingAs($user)->get(route('analytics.overdue'));

        $response->assertInertia(fn ($page) => $page->has('overdueTasks', 0));
    }
}
