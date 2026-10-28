<?php

namespace Tests\Feature\Admin;

use App\Models\Unit;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Unit $otherUnit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = Unit::factory()->create(['name' => 'Pusbin']);
        $this->otherUnit = Unit::factory()->create(['name' => 'SDM']);
        $this->admin = $this->userWithRole('admin', $this->unit);
    }

    private function userWithRole(string $role, Unit $unit, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['unit_id' => $unit->id, 'role' => $role], $attributes));
        $user->syncRoles([$role]);

        return $user;
    }

    private function webhook(Unit $unit, string $name = 'Hook'): Webhook
    {
        return Webhook::create([
            'unit_id' => $unit->id, 'name' => $name, 'url' => 'https://example.com/hook',
            'events' => ['task.created'], 'secret' => str_repeat('s', 40), 'is_active' => true,
        ]);
    }

    public function test_unit_admin_can_open_user_and_webhook_pages_but_not_superadmin_pages(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.webhooks.index'))->assertOk();

        foreach (['admin.units.index', 'admin.settings.edit', 'admin.audit-logs.index', 'admin.ai-usage.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertForbidden();
        }
    }

    public function test_regular_user_still_has_no_admin_access(): void
    {
        $user = $this->userWithRole('user', $this->unit);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.webhooks.index'))->assertForbidden();
    }

    public function test_unit_admin_only_lists_users_of_their_unit_and_search_cannot_escape_it(): void
    {
        $this->userWithRole('user', $this->unit, ['name' => 'Budi Pusbin']);
        $this->userWithRole('user', $this->otherUnit, ['name' => 'Budi SDM']);

        $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => 'Budi']))
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Budi Pusbin')
                ->has('units', 1)
                ->where('units.0.id', $this->unit->id)
                ->where('assignableRoles', ['user', 'admin']));
    }

    public function test_unit_admin_creates_users_only_in_their_unit_and_never_as_superadmin(): void
    {
        $payload = ['name' => 'Baru', 'email' => 'baru@example.com', 'password' => 'password123', 'role' => 'user'];

        // unit_id of another unit is ignored: the user lands in the admin's unit.
        $this->actingAs($this->admin)->post(route('admin.users.store'), $payload + ['unit_id' => $this->otherUnit->id])->assertRedirect();
        $this->assertSame($this->unit->id, User::where('email', 'baru@example.com')->value('unit_id'));

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), ['email' => 'super@example.com', 'role' => 'superadmin'] + $payload)
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'super@example.com']);
    }

    public function test_unit_admin_can_edit_and_delete_own_unit_users_but_not_other_units_or_superadmins(): void
    {
        $own = $this->userWithRole('user', $this->unit);
        $foreign = $this->userWithRole('user', $this->otherUnit);
        $superadmin = $this->userWithRole('superadmin', $this->unit);

        $update = fn (User $target, array $data = []) => $this->actingAs($this->admin)->put(route('admin.users.update', $target), array_merge([
            'name' => 'Diubah', 'email' => $target->email, 'role' => 'admin',
        ], $data));

        $update($own, ['unit_id' => $this->otherUnit->id])->assertRedirect();
        $this->assertSame('admin', $own->fresh()->role, 'unit admins may promote within their unit');
        $this->assertSame($this->unit->id, $own->fresh()->unit_id, 'but cannot move users to another unit');

        $update($foreign)->assertForbidden();
        $update($superadmin, ['role' => 'superadmin'])->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.users.edit', $superadmin))->assertForbidden();

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $foreign))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $own))->assertRedirect();
        $this->assertModelMissing($own);
    }

    public function test_users_list_flags_which_rows_the_admin_can_manage(): void
    {
        $this->userWithRole('superadmin', $this->unit, ['name' => 'Zed Super']);

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertInertia(function ($page) {
                $rows = collect($page->toArray()['props']['users']['data'])->keyBy('name');
                $this->assertFalse($rows['Zed Super']['can_manage']);
                $this->assertTrue($rows[$this->admin->name]['can_manage']);
            });
    }

    public function test_unit_admin_manages_only_their_units_webhooks(): void
    {
        $own = $this->webhook($this->unit, 'Hook Pusbin');
        $foreign = $this->webhook($this->otherUnit, 'Hook SDM');

        $this->actingAs($this->admin)->get(route('admin.webhooks.index'))
            ->assertInertia(fn ($page) => $page->has('webhooks', 1)->where('webhooks.0.name', 'Hook Pusbin')->has('units', 1));

        $this->actingAs($this->admin)->patch(route('admin.webhooks.update', $foreign), ['is_active' => false])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('admin.webhooks.destroy', $foreign))->assertForbidden();
        $this->actingAs($this->admin)->patch(route('admin.webhooks.update', $own), ['is_active' => false])->assertRedirect();
        $this->assertFalse($own->fresh()->is_active);

        $payload = ['name' => 'Baru', 'url' => 'https://example.com/x', 'events' => ['task.created']];
        $this->actingAs($this->admin)->post(route('admin.webhooks.store'), $payload + ['unit_id' => $this->otherUnit->id])->assertSessionHasErrors('unit_id');
        $this->actingAs($this->admin)->post(route('admin.webhooks.store'), $payload + ['unit_id' => $this->unit->id])->assertRedirect();
        $this->assertSame(2, Webhook::where('unit_id', $this->unit->id)->count());
    }

    public function test_superadmin_keeps_full_access(): void
    {
        $superadmin = $this->userWithRole('superadmin', $this->unit);
        $this->webhook($this->otherUnit);
        $this->userWithRole('user', $this->otherUnit);

        $this->actingAs($superadmin)->get(route('admin.users.index'))
            ->assertInertia(fn ($page) => $page->where('assignableRoles', ['user', 'admin', 'superadmin'])->has('units', 2));
        $this->actingAs($superadmin)->get(route('admin.webhooks.index'))->assertInertia(fn ($page) => $page->has('webhooks', 1));
        $this->actingAs($superadmin)->get(route('admin.units.index'))->assertOk();
    }
}
