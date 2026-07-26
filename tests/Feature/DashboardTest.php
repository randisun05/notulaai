<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_stats_are_scoped_to_users_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Meeting::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'title' => 'Rapat unit saya',
            'date' => now(),
            'agenda' => 'Agenda',
            'attendees' => 'Semua',
            'status' => 'Dijadwalkan',
        ]);
        Meeting::create([
            'user_id' => $user->id,
            'unit_id' => $otherUnit->id,
            'title' => 'Rapat unit lain',
            'date' => now(),
            'agenda' => 'Agenda',
            'attendees' => 'Semua',
            'status' => 'Dijadwalkan',
        ]);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task selesai', 'status' => 'Done']);
        Task::create(['unit_id' => $unit->id, 'title' => 'Task overdue', 'status' => 'Todo', 'deadline' => now()->subDays(2)->toDateString()]);
        Task::create(['unit_id' => $otherUnit->id, 'title' => 'Task unit lain', 'status' => 'Done']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.total_meetings', 1)
            ->where('stats.tasks_completed', 1)
            ->where('stats.tasks_overdue', 1)
            ->has('weeklyMeetings', 8)
            ->has('taskStatusBreakdown', 6)
        );
    }

    public function test_superadmin_sees_stats_across_all_units(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $superadmin = User::factory()->create(['unit_id' => $unit->id]);
        $superadmin->syncRoles(['superadmin']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task A', 'status' => 'Done']);
        Task::create(['unit_id' => $otherUnit->id, 'title' => 'Task B', 'status' => 'Done']);

        $response = $this->actingAs($superadmin)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('stats.tasks_completed', 2));
    }
}
