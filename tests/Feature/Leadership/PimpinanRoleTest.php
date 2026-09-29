<?php

namespace Tests\Feature\Leadership;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Peran Pimpinan: melihat rapat & Task semua unit, tapi tidak bisa mengubah /
 * ikut menulis di unit lain dan tidak bisa membuka panel admin.
 */
class PimpinanRoleTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unitA;

    private Unit $unitB;

    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->unitA = Unit::create(['name' => 'Unit A']);
        $this->unitB = Unit::create(['name' => 'Unit B']);
        $this->pimpinan = User::factory()->create(['unit_id' => $this->unitA->id, 'role' => 'pimpinan']);
        $this->pimpinan->assignRole('pimpinan');
    }

    private function meetingIn(Unit $unit, string $status = 'Selesai Diproses'): Meeting
    {
        $owner = User::factory()->create(['unit_id' => $unit->id]);

        return Meeting::create([
            'user_id' => $owner->id, 'unit_id' => $unit->id, 'title' => 'Rapat '.$unit->name,
            'date' => '2026-09-28 09:00', 'status' => $status, 'transcript' => 'Isi rapat', 'summary' => '<p>Ringkas</p>',
        ]);
    }

    public function test_pimpinan_sees_meetings_and_tasks_of_every_unit(): void
    {
        $this->meetingIn($this->unitA);
        $other = $this->meetingIn($this->unitB);
        Task::create(['unit_id' => $this->unitB->id, 'title' => 'Task unit B', 'priority' => 'Medium', 'status' => 'Todo']);

        $this->assertSame(2, Meeting::visibleTo($this->pimpinan)->count());
        $this->assertSame(1, Task::visibleTo($this->pimpinan)->count());

        $this->actingAs($this->pimpinan)->get(route('meetings.show', $other))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.update', false)->where('can.delete', false));
    }

    public function test_pimpinan_cannot_modify_or_participate_in_another_units_meeting(): void
    {
        $other = $this->meetingIn($this->unitB);
        $this->actingAs($this->pimpinan);

        $this->put(route('meetings.update', $other), ['title' => 'X', 'date' => '2026-09-28 09:00'])->assertForbidden();
        $this->delete(route('meetings.destroy', $other))->assertForbidden();
        $this->post(route('meetings.comments.store', $other), ['body' => 'Halo'])->assertForbidden();
        $this->postJson(route('meetings.chat.store', $other), ['question' => 'Apa?'])->assertForbidden();
        $this->post(route('meetings.minutes.generate', $other))->assertForbidden();
        $this->post(route('meetings.attendance.store', $other), ['name' => 'Tamu'])->assertForbidden();
        $this->postJson(route('meetings.live.markers', $other), ['type' => 'keputusan'])->assertForbidden();

        $comment = ForumComment::create(['meeting_id' => $other->id, 'user_id' => $other->user_id, 'body' => 'Hai']);
        $this->post(route('comments.reactions.toggle', $comment), ['emoji' => '👍'])->assertForbidden();
        $this->assertSame(0, ForumComment::where('user_id', $this->pimpinan->id)->count());
    }

    public function test_pimpinan_can_view_but_not_update_another_units_task(): void
    {
        $task = Task::create(['unit_id' => $this->unitB->id, 'title' => 'Task unit B', 'priority' => 'Medium', 'status' => 'Todo']);
        $this->actingAs($this->pimpinan);

        $this->get(route('tasks.show', $task))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canUpdate', false)->where('canManage', false)->where('canApprove', false));
        $this->patch(route('tasks.update-status', $task), ['status' => 'In Progress'])->assertForbidden();
        $this->assertSame('Todo', $task->fresh()->status);
    }

    public function test_pimpinan_still_works_normally_in_own_unit(): void
    {
        $own = $this->meetingIn($this->unitA);

        $this->actingAs($this->pimpinan)->get(route('meetings.show', $own))
            ->assertInertia(fn (Assert $page) => $page->where('can.update', true));
        $this->post(route('meetings.comments.store', $own), ['body' => 'Catatan pimpinan'])->assertRedirect();
    }

    public function test_pimpinan_has_no_admin_panel(): void
    {
        $this->actingAs($this->pimpinan)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_superadmin_can_grant_pimpinan_role(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $admin->assignRole('superadmin');
        $user = User::factory()->create(['unit_id' => $this->unitB->id]);
        $user->assignRole('user');

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'pimpinan', 'unit_id' => $this->unitB->id,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue($user->fresh()->hasRole('pimpinan'));
        $this->assertTrue($user->fresh()->seesAllUnits());
    }
}
