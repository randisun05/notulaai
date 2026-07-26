<?php

namespace Tests\Feature\Meeting;

use App\Jobs\ProcessMeetingNotula;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessMeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function createMeeting(User $user, Unit $unit): Meeting
    {
        return Meeting::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'title' => 'Rapat Uji Coba',
            'date' => now(),
            'agenda' => 'Agenda uji coba',
            'attendees' => 'Peserta uji coba',
            'status' => 'Dijadwalkan',
        ]);
    }

    public function test_uploading_image_dispatches_processing_job(): void
    {
        Bus::fake();
        Storage::fake('public');

        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $response = $this->actingAs($user)->post(route('meetings.process', $meeting), [
            'type' => 'image',
            'image_file' => UploadedFile::fake()->image('notes.jpg'),
        ]);

        $response->assertRedirect(route('meetings.show', $meeting));
        Bus::assertDispatched(ProcessMeetingNotula::class);
        $this->assertSame('Memproses', $meeting->fresh()->status);
        $this->assertStringStartsWith('image_uploads/', $meeting->fresh()->source_file_path);
    }

    public function test_uploading_unsupported_image_type_is_rejected(): void
    {
        Bus::fake();
        Storage::fake('public');

        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $response = $this->actingAs($user)->post(route('meetings.process', $meeting), [
            'type' => 'image',
            'image_file' => UploadedFile::fake()->create('notes.gif', 10),
        ]);

        $response->assertSessionHasErrors('image_file');
        Bus::assertNotDispatched(ProcessMeetingNotula::class);
    }

    public function test_user_cannot_process_meeting_from_another_unit(): void
    {
        Bus::fake();
        Storage::fake('public');

        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $otherUnitUser = User::factory()->create(['unit_id' => $otherUnit->id]);

        $user = User::factory()->create(['unit_id' => $ownUnit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($otherUnitUser, $otherUnit);

        $response = $this->actingAs($user)->post(route('meetings.process', $meeting), [
            'type' => 'image',
            'image_file' => UploadedFile::fake()->image('notes.jpg'),
        ]);

        $response->assertForbidden();
        Bus::assertNotDispatched(ProcessMeetingNotula::class);
    }
}
