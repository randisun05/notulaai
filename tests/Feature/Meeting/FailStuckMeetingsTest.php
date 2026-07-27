<?php

namespace Tests\Feature\Meeting;

use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailStuckMeetingsTest extends TestCase
{
    use RefreshDatabase;

    private function createMeeting(User $user, Unit $unit, string $status, int $ageInMinutes): Meeting
    {
        $meeting = Meeting::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'title' => 'Rapat Uji Coba',
            'date' => now(),
            'agenda' => 'Agenda uji coba',
            'attendees' => 'Peserta uji coba',
            'status' => $status,
        ]);

        // updated_at bukan mass-assignable, jadi diset lewat query builder
        // (bypass fillable) supaya benar-benar tercatat sebagai "sudah lama".
        Meeting::where('id', $meeting->id)->update(['updated_at' => now()->subMinutes($ageInMinutes)]);

        return $meeting->fresh();
    }

    public function test_meeting_stuck_past_threshold_is_marked_failed(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);

        $stuck = $this->createMeeting($user, $unit, 'Memproses', ageInMinutes: 15);

        $this->artisan('meetings:fail-stuck', ['--minutes' => 10])
            ->assertExitCode(0);

        $this->assertSame('Gagal', $stuck->fresh()->status);
    }

    public function test_meeting_still_within_threshold_is_left_alone(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);

        $recent = $this->createMeeting($user, $unit, 'Memproses', ageInMinutes: 2);

        $this->artisan('meetings:fail-stuck', ['--minutes' => 10])
            ->assertExitCode(0);

        $this->assertSame('Memproses', $recent->fresh()->status);
    }

    public function test_meetings_not_in_processing_status_are_untouched(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);

        $done = $this->createMeeting($user, $unit, 'Selesai Diproses', ageInMinutes: 30);

        $this->artisan('meetings:fail-stuck', ['--minutes' => 10])
            ->assertExitCode(0);

        $this->assertSame('Selesai Diproses', $done->fresh()->status);
    }
}
