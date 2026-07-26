<?php

namespace Tests\Feature\Meeting;

use App\Mail\AiGeneratedEmail;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailGeneratorTest extends TestCase
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
            'status' => 'Selesai Diproses',
            'summary' => '<p>Ringkasan uji coba</p>',
        ]);
    }

    public function test_generate_rejects_invalid_purpose(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $response = $this->actingAs($user)->post(route('meetings.emails.generate', $meeting), [
            'purpose' => 'not-a-real-purpose',
        ]);

        $response->assertSessionHasErrors('purpose');
    }

    public function test_generate_forbidden_for_user_outside_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $meetingOwner = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider = User::factory()->create(['unit_id' => $ownUnit->id]);
        $outsider->syncRoles(['user']);

        $meeting = $this->createMeeting($meetingOwner, $otherUnit);

        $response = $this->actingAs($outsider)->post(route('meetings.emails.generate', $meeting), [
            'purpose' => 'follow_up',
        ]);

        $response->assertForbidden();
    }

    public function test_send_only_delivers_to_recipients_within_meeting_unit(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $sameUnitRecipient = User::factory()->create(['unit_id' => $unit->id]);
        $otherUnitUser = User::factory()->create(['unit_id' => $otherUnit->id]);

        $meeting = $this->createMeeting($user, $unit);

        $response = $this->actingAs($user)->post(route('meetings.emails.send', $meeting), [
            'subject' => 'Follow up rapat',
            'body' => '<p>Halo</p>',
            'recipient_ids' => [$sameUnitRecipient->id, $otherUnitUser->id],
        ]);

        $response->assertRedirect();
        Mail::assertQueued(AiGeneratedEmail::class, 1);
        Mail::assertQueued(AiGeneratedEmail::class, function ($mail) use ($sameUnitRecipient) {
            return $mail->hasTo($sameUnitRecipient->email);
        });
    }

    public function test_send_requires_at_least_one_recipient(): void
    {
        Mail::fake();

        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);
        $meeting = $this->createMeeting($user, $unit);

        $response = $this->actingAs($user)->post(route('meetings.emails.send', $meeting), [
            'subject' => 'Follow up rapat',
            'body' => '<p>Halo</p>',
            'recipient_ids' => [],
        ]);

        $response->assertSessionHasErrors('recipient_ids');
        Mail::assertNothingSent();
    }

    public function test_send_forbidden_for_user_outside_unit(): void
    {
        Mail::fake();

        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $meetingOwner = User::factory()->create(['unit_id' => $otherUnit->id]);
        $outsider = User::factory()->create(['unit_id' => $ownUnit->id]);
        $outsider->syncRoles(['user']);
        $recipient = User::factory()->create(['unit_id' => $otherUnit->id]);

        $meeting = $this->createMeeting($meetingOwner, $otherUnit);

        $response = $this->actingAs($outsider)->post(route('meetings.emails.send', $meeting), [
            'subject' => 'Follow up rapat',
            'body' => '<p>Halo</p>',
            'recipient_ids' => [$recipient->id],
        ]);

        $response->assertForbidden();
        Mail::assertNothingSent();
    }
}
