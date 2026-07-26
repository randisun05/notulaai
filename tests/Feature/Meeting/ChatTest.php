<?php

namespace Tests\Feature\Meeting;

use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
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

    public function test_question_is_required(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $response = $this->actingAs($user)->postJson(route('meetings.chat.store', $meeting), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('question');
    }

    public function test_cannot_chat_on_meeting_without_transcript(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit, ['transcript' => null, 'status' => 'Dijadwalkan']);

        $response = $this->actingAs($user)->postJson(route('meetings.chat.store', $meeting), [
            'question' => 'Apa keputusan rapat?',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Rapat ini belum memiliki transkrip untuk ditanyakan.']);
    }

    public function test_user_from_another_unit_cannot_chat(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $meetingOwner = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider = User::factory()->create(['unit_id' => $ownUnit->id]);
        $outsider->syncRoles(['user']);

        $meeting = $this->createMeeting($meetingOwner, $otherUnit);

        $response = $this->actingAs($outsider)->postJson(route('meetings.chat.store', $meeting), [
            'question' => 'Apa keputusan rapat?',
        ]);

        $response->assertForbidden();
    }
}
