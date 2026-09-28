<?php

namespace Tests\Feature\Admin;

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastSuperadminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function superadmin(): User
    {
        $user = User::factory()->create(['unit_id' => Unit::factory()->create()->id, 'role' => 'superadmin']);
        $user->syncRoles(['superadmin']);

        return $user;
    }

    public function test_the_only_superadmin_cannot_demote_themselves(): void
    {
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->put(route('admin.users.update', $superadmin), [
            'name' => $superadmin->name,
            'email' => $superadmin->email,
            'role' => 'admin',
        ])->assertSessionHas('error');

        $this->assertTrue($superadmin->fresh()->hasRole('superadmin'));
    }

    public function test_a_superadmin_can_be_demoted_while_another_remains(): void
    {
        $superadmin = $this->superadmin();
        $other = $this->superadmin();

        $this->actingAs($superadmin)->put(route('admin.users.update', $other), [
            'name' => $other->name,
            'email' => $other->email,
            'role' => 'admin',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($other->fresh()->hasRole('admin'));
    }

    public function test_the_only_superadmin_cannot_delete_their_own_account(): void
    {
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertModelExists($superadmin);
    }
}
