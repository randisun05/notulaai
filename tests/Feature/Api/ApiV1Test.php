<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessMeetingNotula;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userInUnit(Unit $unit, string $role = 'user'): User
    {
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function asToken(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test')->plainTextToken);
    }

    private function meeting(Unit $unit, User $creator, array $attributes = []): Meeting
    {
        return Meeting::create(['title' => 'Rapat', 'date' => '2026-10-01 09:00:00', 'status' => 'Dijadwalkan', 'unit_id' => $unit->id, 'user_id' => $creator->id, ...$attributes]);
    }

    public function test_api_requires_a_token(): void
    {
        $this->get('/api/v1/meetings')->assertUnauthorized();
    }

    public function test_user_endpoint_returns_profile_with_role_and_unit(): void
    {
        $unit = Unit::factory()->create(['name' => 'Biro Umum']);
        $user = $this->userInUnit($unit, 'admin');

        $this->asToken($user)->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.unit.name', 'Biro Umum');
    }

    public function test_meeting_list_is_scoped_to_the_users_unit(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $this->meeting($unit, $user, ['title' => 'Milik unit']);
        $this->meeting($otherUnit, $this->userInUnit($otherUnit), ['title' => 'Unit lain']);

        $this->asToken($user)->getJson('/api/v1/meetings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Milik unit')
            ->assertJsonMissingPath('data.0.transcript');
    }

    public function test_meeting_from_another_unit_is_forbidden(): void
    {
        $user = $this->userInUnit(Unit::factory()->create());
        $otherUnit = Unit::factory()->create();
        $meeting = $this->meeting($otherUnit, $this->userInUnit($otherUnit));

        $this->asToken($user)->getJson("/api/v1/meetings/{$meeting->id}")->assertForbidden();
    }

    public function test_meeting_detail_includes_summary_transcript_and_action_items(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $meeting = $this->meeting($unit, $user, ['status' => 'Selesai Diproses', 'summary' => '<p>Ringkas</p>', 'transcript' => 'Isi']);
        $meeting->actionItems()->create(['title' => 'Kirim laporan', 'order' => 0]);

        $this->asToken($user)->getJson("/api/v1/meetings/{$meeting->id}")
            ->assertOk()
            ->assertJsonPath('data.summary', '<p>Ringkas</p>')
            ->assertJsonPath('data.transcript', 'Isi')
            ->assertJsonPath('data.action_items.0.title', 'Kirim laporan');
    }

    public function test_meeting_can_be_created_in_the_users_unit(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);

        $this->asToken($user)->postJson('/api/v1/meetings', ['title' => 'Rapat API', 'date' => '2026-10-02 13:00'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'Dijadwalkan')
            ->assertJsonPath('data.unit_id', $unit->id);
    }

    public function test_validation_errors_are_json_without_accept_header(): void
    {
        $user = $this->userInUnit(Unit::factory()->create());

        $this->asToken($user)->post('/api/v1/meetings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'date']);
    }

    public function test_transcript_submission_starts_processing(): void
    {
        Bus::fake();
        Storage::fake('public');
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $meeting = $this->meeting($unit, $user);

        $this->asToken($user)->postJson("/api/v1/meetings/{$meeting->id}/transcript", ['transcript' => 'Budi: kita mulai rapat.'])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'Memproses');

        Bus::assertDispatched(ProcessMeetingNotula::class);
        Storage::disk('public')->assertExists($meeting->fresh()->source_file_path);
    }

    public function test_transcript_submission_is_refused_for_a_processed_meeting(): void
    {
        Bus::fake();
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $meeting = $this->meeting($unit, $user, ['status' => 'Selesai Diproses']);

        $this->asToken($user)->postJson("/api/v1/meetings/{$meeting->id}/transcript", ['transcript' => 'x'])
            ->assertStatus(409);

        Bus::assertNothingDispatched();
    }

    public function test_task_list_can_filter_to_my_tasks(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Punya saya', 'status' => 'Todo']);
        Task::create(['unit_id' => $unit->id, 'title' => 'Punya orang', 'status' => 'Todo']);

        $this->asToken($user)->getJson('/api/v1/tasks?assigned_to_me=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Punya saya');
    }

    public function test_task_status_can_be_updated_with_the_same_rules_as_the_web(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Tugas', 'status' => 'Todo']);

        $this->asToken($user)->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'In Progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'In Progress');

        $this->asToken($user)->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'Done'])
            ->assertStatus(422);

        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'type' => 'task.status_changed']);
    }

    public function test_regular_user_cannot_reopen_a_done_task_via_api(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userInUnit($unit);
        $task = Task::create(['unit_id' => $unit->id, 'assignee_id' => $user->id, 'title' => 'Tugas', 'status' => 'Done']);

        $this->asToken($user)->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'Todo'])->assertForbidden();

        $this->assertSame('Done', $task->fresh()->status);
    }
}
