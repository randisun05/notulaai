<?php

namespace Tests\Feature\Meeting;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionItemsRegenerateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function createMeeting(User $user, Unit $unit, array $overrides = []): Meeting
    {
        return Meeting::create(array_merge([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'title' => 'Rapat Uji Coba',
            'date' => now(),
            'agenda' => 'Agenda uji coba',
            'attendees' => 'Peserta uji coba',
            'status' => 'Selesai Diproses',
            'transcript' => 'Andi: Kita sepakat proyek selesai bulan depan.',
            'summary' => '<p>Ringkasan</p>',
        ], $overrides));
    }

    private function fakeAiResponse(string $json): void
    {
        $provider = $this->createMock(TextGenerationProvider::class);
        $provider->method('generate')->willReturn(new AiTextResult($json, 'fake', 'fake-model'));

        $this->mock(AiManager::class, function ($mock) use ($provider) {
            $mock->shouldReceive('activeTextProvider')->andReturn('fake');
            $mock->shouldReceive('text')->andReturn($provider);
        });
    }

    public function test_guest_cannot_regenerate_action_items(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $meeting = $this->createMeeting($user, $unit);

        $this->post(route('meetings.action-items.regenerate', $meeting))->assertRedirect(route('login'));
    }

    public function test_user_from_another_unit_cannot_regenerate_action_items(): void
    {
        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $owner = User::factory()->create(['unit_id' => $unit->id]);
        $outsider = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider->syncRoles(['user']);
        $meeting = $this->createMeeting($owner, $unit);

        $this->actingAs($outsider)->post(route('meetings.action-items.regenerate', $meeting))->assertForbidden();
    }

    public function test_regeneration_replaces_old_items_without_duplicating(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        MeetingActionItem::create(['meeting_id' => $meeting->id, 'title' => 'Item lama 1', 'order' => 0]);
        MeetingActionItem::create(['meeting_id' => $meeting->id, 'title' => 'Item lama 2', 'order' => 1]);

        $this->fakeAiResponse(json_encode([
            ['title' => 'Item baru 1', 'assignee_name' => 'Andi', 'deadline' => null],
            ['title' => 'Item baru 2', 'assignee_name' => null, 'deadline' => null],
        ]));

        $response = $this->actingAs($user)->post(route('meetings.action-items.regenerate', $meeting));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $items = $meeting->actionItems()->orderBy('order')->get();
        $this->assertCount(2, $items);
        $this->assertSame(['Item baru 1', 'Item baru 2'], $items->pluck('title')->all());
        $this->assertDatabaseMissing('meeting_action_items', ['title' => 'Item lama 1']);
        $this->assertDatabaseMissing('meeting_action_items', ['title' => 'Item lama 2']);
    }

    public function test_regenerating_twice_never_leaves_duplicate_items(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $this->fakeAiResponse(json_encode([
            ['title' => 'Item konsisten', 'assignee_name' => null, 'deadline' => null],
        ]));

        $this->actingAs($user)->post(route('meetings.action-items.regenerate', $meeting));
        $this->actingAs($user)->post(route('meetings.action-items.regenerate', $meeting));

        $this->assertCount(1, $meeting->actionItems()->get());
    }

    public function test_cannot_regenerate_without_transcript(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit, ['transcript' => null]);

        $response = $this->actingAs($user)->post(route('meetings.action-items.regenerate', $meeting));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_cannot_regenerate_when_an_item_already_became_a_task(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        MeetingActionItem::create(['meeting_id' => $meeting->id, 'title' => 'Sudah jadi task', 'converted_to_task' => true, 'order' => 0]);

        $response = $this->actingAs($user)->post(route('meetings.action-items.regenerate', $meeting));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('meeting_action_items', ['title' => 'Sudah jadi task']);
    }
}
