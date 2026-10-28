<?php

namespace Tests\Feature\Api;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Unit $otherUnit;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = Unit::factory()->create(['name' => 'Pusbin']);
        $this->otherUnit = Unit::factory()->create(['name' => 'SDM']);
        $this->user = User::factory()->create(['unit_id' => $this->unit->id]);
        $this->user->syncRoles(['user']);
        $this->token = $this->user->createToken('integrasi')->plainTextToken;
    }

    private function api(string $uri, ?string $token = null)
    {
        // No Accept header on purpose: API errors must be JSON regardless.
        return $this->withToken($token ?? $this->token)->get($uri);
    }

    private function meeting(Unit $unit, array $attributes = []): Meeting
    {
        return Meeting::create(array_merge([
            'user_id' => $this->user->id,
            'unit_id' => $unit->id,
            'title' => 'Rapat',
            'date' => '2026-10-01',
            'agenda' => 'Agenda',
            'attendees' => 'Semua',
            'status' => 'Selesai Diproses',
            'summary' => '<p>Ringkasan</p>',
            'transcript' => 'transkrip panjang',
        ], $attributes));
    }

    public function test_requests_without_a_valid_token_get_a_json_401(): void
    {
        $this->get('/api/v1/meetings')->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
        $this->api('/api/v1/meetings', 'bogus|token')->assertStatus(401);
    }

    public function test_me_returns_the_token_owner(): void
    {
        $this->api('/api/v1/me')
            ->assertOk()
            ->assertJson(['data' => [
                'id' => $this->user->id,
                'role' => 'user',
                'unit' => ['id' => $this->unit->id, 'name' => 'Pusbin'],
            ]]);
    }

    public function test_meeting_list_is_scoped_to_the_users_unit_and_paginated(): void
    {
        $this->meeting($this->unit, ['title' => 'Rapat kita']);
        $this->meeting($this->otherUnit, ['title' => 'Rapat unit lain']);

        $response = $this->api('/api/v1/meetings')->assertOk();

        $this->assertSame(['Rapat kita'], array_column($response->json('data'), 'title'));
        $response->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);
        $this->assertArrayNotHasKey('transcript', $response->json('data.0'));
    }

    public function test_meeting_list_filters_by_status_search_and_date(): void
    {
        $this->meeting($this->unit, ['title' => 'Anggaran Q4', 'date' => '2026-10-10']);
        $this->meeting($this->unit, ['title' => 'Anggaran Q1', 'date' => '2026-01-10']);
        $this->meeting($this->unit, ['title' => 'Belum diproses', 'status' => 'Dijadwalkan', 'date' => '2026-10-11']);

        $titles = fn (string $query) => array_column($this->api("/api/v1/meetings?{$query}")->assertOk()->json('data'), 'title');

        $this->assertSame(['Belum diproses'], $titles('status=Dijadwalkan'));
        $this->assertEqualsCanonicalizing(['Anggaran Q4', 'Anggaran Q1'], $titles('search=Anggaran'));
        $this->assertSame(['Belum diproses', 'Anggaran Q4'], $titles('from=2026-10-01'));
    }

    public function test_invalid_filters_return_a_json_422(): void
    {
        $this->api('/api/v1/meetings?status=Nope&per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'per_page']);
    }

    public function test_meeting_detail_includes_action_items_and_transcript_only_on_request(): void
    {
        $meeting = $this->meeting($this->unit);
        MeetingActionItem::create(['meeting_id' => $meeting->id, 'title' => 'Kirim laporan', 'assignee_name' => 'Budi', 'deadline' => '2026-10-20']);

        $this->api("/api/v1/meetings/{$meeting->id}")
            ->assertOk()
            ->assertJsonPath('data.summary_html', '<p>Ringkasan</p>')
            ->assertJsonPath('data.action_items.0.title', 'Kirim laporan')
            ->assertJsonPath('data.action_items.0.deadline', '2026-10-20')
            ->assertJsonMissingPath('data.transcript');

        $this->api("/api/v1/meetings/{$meeting->id}?include=transcript")
            ->assertJsonPath('data.transcript', 'transkrip panjang');
    }

    public function test_another_units_meeting_or_task_is_forbidden(): void
    {
        $meeting = $this->meeting($this->otherUnit);
        $task = Task::create(['unit_id' => $this->otherUnit->id, 'title' => 'Rahasia', 'status' => 'Todo']);

        $this->api("/api/v1/meetings/{$meeting->id}")->assertForbidden()->assertJsonStructure(['message']);
        $this->api("/api/v1/tasks/{$task->id}")->assertForbidden();
        $this->api('/api/v1/meetings/999999')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_task_list_is_scoped_and_filterable(): void
    {
        $assignee = User::factory()->create(['unit_id' => $this->unit->id]);
        Task::create(['unit_id' => $this->unit->id, 'title' => 'Telat', 'status' => 'In Progress', 'deadline' => now()->subDays(3)->toDateString()]);
        Task::create(['unit_id' => $this->unit->id, 'title' => 'Selesai telat', 'status' => 'Done', 'deadline' => now()->subDays(3)->toDateString()]);
        Task::create(['unit_id' => $this->unit->id, 'title' => 'Punya Budi', 'status' => 'Todo', 'assignee_id' => $assignee->id]);
        Task::create(['unit_id' => $this->otherUnit->id, 'title' => 'Unit lain', 'status' => 'Todo']);

        $titles = fn (string $query = '') => array_column($this->api("/api/v1/tasks?{$query}")->assertOk()->json('data'), 'title');

        $this->assertEqualsCanonicalizing(['Telat', 'Selesai telat', 'Punya Budi'], $titles());
        $this->assertSame(['Telat'], $titles('overdue=1'));
        $this->assertSame(['Punya Budi'], $titles("assignee_id={$assignee->id}"));
        $this->assertSame(['Selesai telat'], $titles('status=Done'));
    }

    public function test_task_detail_has_assignee_meeting_and_computed_flags(): void
    {
        $assignee = User::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Budi']);
        $meeting = $this->meeting($this->unit, ['title' => 'Rapat Q4']);
        $task = Task::create([
            'unit_id' => $this->unit->id, 'meeting_id' => $meeting->id, 'assignee_id' => $assignee->id,
            'title' => 'Kirim laporan', 'status' => 'Todo', 'deadline' => now()->subDay()->toDateString(),
        ]);

        $this->api("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.assignee.name', 'Budi')
            ->assertJsonPath('data.meeting.title', 'Rapat Q4')
            ->assertJsonPath('data.is_overdue', true)
            ->assertJsonPath('data.url', route('tasks.show', $task->id));
    }

    public function test_superadmin_token_sees_every_unit(): void
    {
        $superadmin = User::factory()->create(['unit_id' => $this->unit->id]);
        $superadmin->syncRoles(['superadmin']);
        $token = $superadmin->createToken('admin')->plainTextToken;

        $this->meeting($this->unit);
        $this->meeting($this->otherUnit);

        $this->assertCount(2, $this->api('/api/v1/meetings', $token)->json('data'));
    }

    public function test_revoked_token_stops_working(): void
    {
        $this->api('/api/v1/me')->assertOk();

        $this->user->tokens()->delete();
        $this->app['auth']->forgetGuards();

        $this->api('/api/v1/me')->assertStatus(401);
    }
}
