<?php

namespace Tests\Feature\Leadership;

use App\Models\Meeting;
use App\Models\MeetingMinutes;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeadershipDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role, Unit $unit): User
    {
        $user = User::factory()->create(['unit_id' => $unit->id, 'role' => $role]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_only_pimpinan_and_superadmin_open_the_leadership_dashboard(): void
    {
        $unit = Unit::create(['name' => 'Bidang Jalan']);

        $this->actingAs($this->user('user', $unit))->get(route('leadership.index'))->assertForbidden();
        $this->actingAs($this->user('admin', $unit))->get(route('leadership.index'))->assertForbidden();
        $this->actingAs($this->user('pimpinan', $unit))->get(route('leadership.index'))->assertOk();
        $this->actingAs($this->user('superadmin', $unit))->get(route('leadership.index'))->assertOk();
    }

    public function test_dashboard_aggregates_every_unit(): void
    {
        $this->travelTo('2026-09-29 10:00:00');
        $roads = Unit::create(['name' => 'Bidang Jalan']);
        $water = Unit::create(['name' => 'Bidang Air']);
        $owner = $this->user('user', $roads);

        $recent = Meeting::create(['user_id' => $owner->id, 'unit_id' => $roads->id, 'title' => 'Rapat Jembatan', 'date' => '2026-09-20T09:00', 'status' => 'Selesai Diproses']);
        Meeting::create(['user_id' => $owner->id, 'unit_id' => $roads->id, 'title' => 'Rapat Lama', 'date' => '2026-01-10T09:00', 'status' => 'Selesai Diproses']);
        Meeting::create(['user_id' => $owner->id, 'unit_id' => $water->id, 'title' => 'Rapat Pipa', 'date' => '2026-09-25T09:00', 'status' => 'Dijadwalkan']);
        $recent->decisions()->create(['unit_id' => $roads->id, 'text' => 'Anggaran 4,2 M', 'source' => 'notula']);
        $recent->minutes()->create(['status' => MeetingMinutes::STATUS_APPROVED]);
        $pipes = Meeting::where('title', 'Rapat Pipa')->first();
        $pipes->minutes()->create(['status' => MeetingMinutes::STATUS_SUBMITTED, 'submitted_at' => now(), 'chairperson_name' => 'Kepala Dinas']);

        Task::create(['unit_id' => $roads->id, 'meeting_id' => $recent->id, 'title' => 'Survei', 'priority' => 'High', 'status' => 'In Progress', 'deadline' => '2026-09-20']);
        Task::create(['unit_id' => $roads->id, 'title' => 'RAB', 'priority' => 'High', 'status' => 'Done']);
        Task::create(['unit_id' => $water->id, 'title' => 'Ganti pipa', 'priority' => 'Low', 'status' => 'Todo', 'deadline' => '2026-10-20']);

        $this->actingAs($this->user('pimpinan', $water))->get(route('leadership.index', ['days' => 30]))
            ->assertInertia(fn (Assert $page) => $page->component('Leadership/Index')
                ->where('days', 30)
                ->where('units.1.name', 'Bidang Jalan')
                ->where('units.1.meetings', 1)
                ->where('units.1.processed', 1)
                ->where('units.1.minutes_approved', 1)
                ->where('units.1.decisions', 1)
                ->where('units.1.open_tasks', 1)
                ->where('units.1.overdue_tasks', 1)
                ->where('units.1.completion_rate', 50)
                ->where('units.0.name', 'Bidang Air')
                ->where('units.0.overdue_tasks', 0)
                ->where('totals.meetings', 2)
                ->where('totals.open_tasks', 2)
                ->where('overdue.0.title', 'Survei')
                ->where('overdue.0.days_overdue', 9)
                ->where('overdue.0.meeting.title', 'Rapat Jembatan')
                ->where('recentDecisions.0.text', 'Anggaran 4,2 M')
                ->where('pendingMinutes.0.meeting.title', 'Rapat Pipa')
                ->where('pendingMinutes.0.chairperson', 'Kepala Dinas'));

        $this->get(route('leadership.index', ['days' => 365]))
            ->assertInertia(fn (Assert $page) => $page->where('units.1.meetings', 2));
        $this->get(route('leadership.index', ['days' => 7]))
            ->assertInertia(fn (Assert $page) => $page->where('days', 90)); // periode tak dikenal → default
    }
}
