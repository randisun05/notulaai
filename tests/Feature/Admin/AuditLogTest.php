<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function superadmin(): User
    {
        $unit = Unit::factory()->create();
        $superadmin = User::factory()->create(['unit_id' => $unit->id]);
        $superadmin->syncRoles(['superadmin']);

        return $superadmin;
    }

    public function test_creating_a_user_writes_an_audit_log(): void
    {
        $superadmin = $this->superadmin();
        $unit = Unit::factory()->create();

        $this->actingAs($superadmin)->post(route('admin.users.store'), [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'role' => 'user',
            'unit_id' => $unit->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superadmin->id,
            'action' => 'user.created',
        ]);
    }

    public function test_creating_a_unit_writes_an_audit_log(): void
    {
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post(route('admin.units.store'), ['name' => 'Unit Baru']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superadmin->id,
            'action' => 'unit.created',
        ]);
    }

    public function test_updating_settings_writes_an_audit_log(): void
    {
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->put(route('admin.settings.update'), [
            'company_name' => 'PT Contoh',
            'ai_text_provider' => 'gemini',
            'ai_transcription_provider' => 'whisper_local',
            'ai_ocr_provider' => 'gemini_ocr',
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superadmin->id,
            'action' => 'setting.updated',
        ]);
    }

    public function test_login_and_logout_write_audit_logs(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.login']);

        $this->actingAs($user)->post('/logout');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.logout']);
    }

    public function test_only_superadmin_can_view_audit_log_page(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertForbidden();

        $superadmin = $this->superadmin();
        AuditLog::create(['action' => 'unit.created', 'description' => 'Test log entry']);

        $response = $this->actingAs($superadmin)->get(route('admin.audit-logs.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
        );
    }

    public function test_audit_log_can_be_filtered_by_action(): void
    {
        $superadmin = $this->superadmin();

        AuditLog::create(['action' => 'unit.created', 'description' => 'Buat unit']);
        AuditLog::create(['action' => 'user.created', 'description' => 'Buat user']);

        $response = $this->actingAs($superadmin)->get(route('admin.audit-logs.index', ['action' => 'unit.created']));

        $response->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'unit.created')
        );
    }
}
