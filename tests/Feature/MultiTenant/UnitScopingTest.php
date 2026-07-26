<?php

namespace Tests\Feature\MultiTenant;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test langsung terhadap trait ScopedToUnit (scopeVisibleTo) yang dipakai
 * bersama oleh Task/Meeting/User — sumber tunggal aturan "unit sendiri saja,
 * kecuali superadmin", menggantikan closure ->when(...) yang sebelumnya
 * diulang manual di tiap controller (TaskController, MeetingController,
 * DashboardController, AnalyticsController, TaskExportController).
 */
class UnitScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_visible_to_scope_restricts_tasks_to_users_own_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task saya', 'status' => 'Todo']);
        Task::create(['unit_id' => $otherUnit->id, 'title' => 'Task unit lain', 'status' => 'Todo']);

        $titles = Task::query()->visibleTo($user)->pluck('title');

        $this->assertTrue($titles->contains('Task saya'));
        $this->assertFalse($titles->contains('Task unit lain'));
    }

    public function test_visible_to_scope_lets_superadmin_see_every_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $superadmin = User::factory()->create(['unit_id' => $unit->id]);
        $superadmin->syncRoles(['superadmin']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task A', 'status' => 'Todo']);
        Task::create(['unit_id' => $otherUnit->id, 'title' => 'Task B', 'status' => 'Todo']);

        $this->assertCount(2, Task::query()->visibleTo($superadmin)->get());
    }

    public function test_visible_to_scope_restricts_meetings_to_users_own_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Meeting::create(['user_id' => $user->id, 'unit_id' => $unit->id, 'title' => 'Rapat saya', 'date' => now(), 'agenda' => 'Agenda', 'attendees' => 'Semua', 'status' => 'Dijadwalkan']);
        Meeting::create(['user_id' => $user->id, 'unit_id' => $otherUnit->id, 'title' => 'Rapat unit lain', 'date' => now(), 'agenda' => 'Agenda', 'attendees' => 'Semua', 'status' => 'Dijadwalkan']);

        $titles = Meeting::query()->visibleTo($user)->pluck('title');

        $this->assertTrue($titles->contains('Rapat saya'));
        $this->assertFalse($titles->contains('Rapat unit lain'));
    }

    public function test_visible_to_scope_restricts_users_to_same_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $colleague = User::factory()->create(['unit_id' => $unit->id]);
        $colleague->syncRoles(['user']);
        $stranger = User::factory()->create(['unit_id' => $otherUnit->id]);
        $stranger->syncRoles(['user']);

        $ids = User::query()->visibleTo($user)->pluck('id');

        $this->assertTrue($ids->contains($colleague->id));
        $this->assertFalse($ids->contains($stranger->id));
    }
}
