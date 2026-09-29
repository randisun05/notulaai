<?php

namespace Tests\Feature\Memory;

use App\Models\Meeting;
use App\Models\Setting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\Meeting\MeetingProcessingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeetingSeriesTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $member;

    private Meeting $first;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
        MemoryFakeText::$prompts = [];
        Config::set('ai.providers.memory_text', ['driver' => MemoryFakeText::class, 'model' => 'fake']);
        Config::set('ai.fallbacks', ['text' => [], 'transcription' => [], 'ocr' => []]);
        Setting::current()->update(['ai_text_provider' => 'memory_text']);

        $this->unit = Unit::create(['name' => 'Bidang Jalan']);
        $this->member = User::factory()->create(['unit_id' => $this->unit->id]);
        $this->member->syncRoles(['user']);

        // Rapat pertama: dua tindak lanjut (satu sudah selesai) dan satu keputusan.
        $this->first = $this->meeting('Rapat Jembatan I', '2026-09-01 09:00:00', 'Selesai Diproses');
        $open = $this->first->actionItems()->create(['title' => 'Survei lokasi jembatan', 'assignee_name' => 'Budi', 'deadline' => '2026-09-10', 'order' => 0]);
        $done = $this->first->actionItems()->create(['title' => 'Kirim undangan konsultan', 'order' => 1]);
        Task::create(['meeting_id' => $this->first->id, 'meeting_action_item_id' => $open->id, 'unit_id' => $this->unit->id, 'title' => 'Survei', 'assignee_name' => 'Budi', 'deadline' => '2026-09-10', 'priority' => 'High', 'status' => 'In Progress']);
        Task::create(['meeting_id' => $this->first->id, 'meeting_action_item_id' => $done->id, 'unit_id' => $this->unit->id, 'title' => 'Undangan', 'priority' => 'Low', 'status' => 'Done']);
        $this->first->decisions()->create(['unit_id' => $this->unit->id, 'text' => 'Jembatan dibangun dengan rangka baja', 'source' => 'notula', 'meeting_action_item_id' => $open->id]);
    }

    private function meeting(string $title, string $date, string $status = 'Dijadwalkan', array $extra = []): Meeting
    {
        return Meeting::create($extra + ['user_id' => $this->member->id, 'unit_id' => $this->unit->id, 'title' => $title, 'date' => $date, 'status' => $status]);
    }

    public function test_a_follow_up_meeting_can_be_scheduled_from_the_previous_one(): void
    {
        $this->actingAs($this->member)->get(route('meetings.create', ['previous' => $this->first->id]))
            ->assertInertia(fn (Assert $page) => $page->component('Meetings/Create')
                ->where('previousMeetingId', $this->first->id)
                ->has('previousOptions', 1));

        $this->post(route('meetings.store'), [
            'title' => 'Rapat Jembatan II', 'date' => '2026-09-15T09:00', 'attendees' => 'Budi', 'agenda' => 'Lanjutan',
            'previous_meeting_id' => $this->first->id,
        ])->assertRedirect();

        $this->assertSame($this->first->id, Meeting::where('title', 'Rapat Jembatan II')->first()->previous_meeting_id);
    }

    public function test_previous_meeting_must_be_from_the_same_unit_and_not_create_a_loop(): void
    {
        $foreign = Meeting::create(['user_id' => $this->member->id, 'unit_id' => Unit::create(['name' => 'Lain'])->id, 'title' => 'X', 'date' => '2026-09-01 09:00', 'status' => 'Dijadwalkan']);

        $this->actingAs($this->member)->post(route('meetings.store'), [
            'title' => 'Y', 'date' => '2026-09-15T09:00', 'previous_meeting_id' => $foreign->id,
        ])->assertSessionHasErrors('previous_meeting_id');

        // I (dijadwalkan ulang) ← II: I tidak boleh dijadikan lanjutan dari II.
        $this->first->update(['status' => 'Dijadwalkan']);
        $second = $this->meeting('Rapat Jembatan II', '2026-09-15 09:00:00', 'Dijadwalkan', ['previous_meeting_id' => $this->first->id]);

        $this->put(route('meetings.update', $this->first), [
            'title' => $this->first->title, 'date' => '2026-09-01T09:00', 'previous_meeting_id' => $second->id,
        ])->assertSessionHasErrors('previous_meeting_id');
        $this->put(route('meetings.update', $this->first), [
            'title' => $this->first->title, 'date' => '2026-09-01T09:00', 'previous_meeting_id' => $this->first->id,
        ])->assertSessionHasErrors('previous_meeting_id');
    }

    public function test_meeting_page_carries_over_open_follow_ups_and_decisions(): void
    {
        $second = $this->meeting('Rapat Jembatan II', '2026-09-15 09:00:00', 'Dijadwalkan', ['previous_meeting_id' => $this->first->id]);
        $third = $this->meeting('Rapat Jembatan III', '2026-09-29 09:00:00', 'Dijadwalkan', ['previous_meeting_id' => $second->id]);

        $this->actingAs($this->member)->get(route('meetings.show', $third))
            ->assertInertia(fn (Assert $page) => $page
                ->where('series.previous.0.title', 'Rapat Jembatan II')
                ->where('series.previous.1.title', 'Rapat Jembatan I')
                ->has('series.open_follow_ups', 1)
                ->where('series.open_follow_ups.0.title', 'Survei lokasi jembatan')
                ->where('series.open_follow_ups.0.status', 'In Progress')
                ->where('series.decisions.0.text', 'Jembatan dibangun dengan rangka baja')
                ->where('series.decisions.0.follow_up.status', 'berjalan'));

        $this->get(route('meetings.show', $this->first))
            ->assertInertia(fn (Assert $page) => $page->where('series.previous', [])->where('series.next.0.title', 'Rapat Jembatan II'));
    }

    public function test_previous_follow_ups_and_decisions_go_into_the_summary_prompt(): void
    {
        $second = $this->meeting('Rapat Jembatan II', '2026-09-15 09:00:00', 'Memproses', ['previous_meeting_id' => $this->first->id]);

        app(MeetingProcessingService::class)->finalize($second, 'Budi: survei lokasi sudah 80 persen.');

        $summaryPrompt = MemoryFakeText::$prompts[0];
        $this->assertStringContainsString('lanjutan dari: "Rapat Jembatan I" (1 September 2026)', $summaryPrompt);
        $this->assertStringContainsString('- Survei lokasi jembatan (PIC: Budi) (tenggat 2026-09-10) [status: In Progress', $summaryPrompt);
        $this->assertStringNotContainsString('Kirim undangan konsultan', $summaryPrompt, 'Tindak lanjut yang sudah Done tidak dibawa.');
        $this->assertStringContainsString('Jembatan dibangun dengan rangka baja', $summaryPrompt);
        $this->assertStringContainsString('Perkembangan Tindak Lanjut Rapat Sebelumnya', $summaryPrompt);
    }

    public function test_a_standalone_meeting_has_no_series_context(): void
    {
        $this->actingAs($this->member)->get(route('meetings.show', $this->first))->assertOk();
        $lonely = $this->meeting('Rapat Lain', '2026-09-20 09:00:00', 'Memproses');

        app(MeetingProcessingService::class)->finalize($lonely, 'isi');

        $this->assertStringNotContainsString('lanjutan dari', MemoryFakeText::$prompts[0]);
    }
}
