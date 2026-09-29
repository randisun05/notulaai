<?php

namespace Tests\Feature\Memory;

use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Models\Setting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\Meeting\DecisionService;
use App\Services\Meeting\MeetingProcessingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DecisionRegisterTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $member;

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
    }

    private function meeting(array $attributes = []): Meeting
    {
        return Meeting::create($attributes + [
            'user_id' => $this->member->id, 'unit_id' => $this->unit->id, 'title' => 'Rapat Jembatan',
            'date' => '2026-09-20 09:00:00', 'status' => 'Memproses',
        ]);
    }

    public function test_decisions_are_extracted_when_the_meeting_is_processed_and_linked_to_follow_ups(): void
    {
        $meeting = $this->meeting();
        $meeting->markers()->create(['type' => 'keputusan', 'at_seconds' => 60, 'note' => 'Anggaran 4,2 M']);

        app(MeetingProcessingService::class)->finalize($meeting, 'Budi: anggaran 4,2 miliar kita sepakati.');

        $decisions = $meeting->fresh()->decisions;
        $this->assertCount(2, $decisions);
        $this->assertSame('ai', $decisions[0]->source);
        $this->assertSame($this->unit->id, $decisions[0]->unit_id);
        $this->assertSame('Susun RAB final jembatan', $decisions[0]->actionItem->title);
        $this->assertNull($decisions[1]->meeting_action_item_id);

        $prompt = collect(MemoryFakeText::$prompts)->first(fn ($p) => str_contains($p, 'KEPUTUSAN / KESIMPULAN'));
        $this->assertStringContainsString('1. Susun RAB final jembatan', $prompt);
        $this->assertStringContainsString('Anggaran 4,2 M', $prompt, 'Penanda keputusan saat rapat ikut dikirim.');

        // Status tindak lanjut mengikuti Task yang lahir dari action item-nya.
        $this->assertSame('belum', $decisions[0]->follow_up['status']);
        Task::create(['meeting_id' => $meeting->id, 'meeting_action_item_id' => $decisions[0]->meeting_action_item_id, 'unit_id' => $this->unit->id,
            'title' => 'Susun RAB', 'priority' => 'Medium', 'status' => 'In Progress']);
        $this->assertSame('berjalan', $decisions[0]->fresh()->follow_up['status']);
        $this->assertSame('tanpa', $decisions[1]->follow_up['status']);
    }

    public function test_approved_minutes_replace_ai_decisions_and_keep_their_follow_up_link(): void
    {
        $meeting = $this->meeting();
        app(MeetingProcessingService::class)->finalize($meeting, 'transkrip');
        $linked = $meeting->decisions()->first()->meeting_action_item_id;

        $chair = User::factory()->create(['unit_id' => $this->unit->id]);
        $chair->syncRoles(['user']);
        $meeting->minutes()->create([
            'status' => 'diajukan', 'submitted_by' => $this->member->id, 'chairperson_id' => $chair->id,
            'decisions' => ['Anggaran pembangunan jembatan ditetapkan sebesar Rp4,2 miliar', 'Lelang dimulai November 2026'],
        ]);

        $this->actingAs($chair)->post(route('meetings.minutes.approve', $meeting))->assertSessionHas('success');

        $decisions = $meeting->fresh()->decisions;
        $this->assertSame(['notula', 'notula'], $decisions->pluck('source')->all());
        $this->assertSame('Anggaran pembangunan jembatan ditetapkan sebesar Rp4,2 miliar', $decisions[0]->text);
        $this->assertSame($linked, $decisions[0]->meeting_action_item_id, 'Rumusan resmi mewarisi kaitan tindak lanjut hasil AI yang mirip.');
        $this->assertNull($decisions[1]->meeting_action_item_id);

        // Ekstraksi AI berikutnya tidak menimpa rumusan resmi.
        app(DecisionService::class)->extract($meeting->fresh());
        $this->assertSame(['notula', 'notula'], $meeting->fresh()->decisions->pluck('source')->all());
    }

    public function test_register_page_is_unit_scoped_searchable_and_filterable(): void
    {
        $meeting = $this->meeting(['status' => 'Selesai Diproses']);
        $item = $meeting->actionItems()->create(['title' => 'Susun RAB', 'order' => 0]);
        Task::create(['meeting_id' => $meeting->id, 'meeting_action_item_id' => $item->id, 'unit_id' => $this->unit->id, 'title' => 'Susun RAB', 'priority' => 'Medium', 'status' => 'Done']);
        $meeting->decisions()->create(['unit_id' => $this->unit->id, 'text' => 'Anggaran jembatan Rp4,2 miliar', 'source' => 'ai', 'meeting_action_item_id' => $item->id]);
        $meeting->decisions()->create(['unit_id' => $this->unit->id, 'text' => 'Evaluasi bulanan', 'source' => 'ai', 'order' => 1]);

        $otherUnit = Unit::create(['name' => 'Bidang Air']);
        $other = Meeting::create(['user_id' => $this->member->id, 'unit_id' => $otherUnit->id, 'title' => 'Rapat Air', 'date' => '2026-09-21 09:00', 'status' => 'Selesai Diproses']);
        $other->decisions()->create(['unit_id' => $otherUnit->id, 'text' => 'Pipa air diganti', 'source' => 'ai']);

        $this->actingAs($this->member)->get(route('decisions.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Decisions/Index')
                ->has('decisions.data', 2)->where('units', []));

        $this->get(route('decisions.index', ['q' => '4,2 miliar']))
            ->assertInertia(fn (Assert $page) => $page->has('decisions.data', 1)
                ->where('decisions.data.0.follow_up.status', 'selesai')
                ->where('decisions.data.0.meeting.title', 'Rapat Jembatan'));
        $this->get(route('decisions.index', ['status' => 'tanpa']))
            ->assertInertia(fn (Assert $page) => $page->has('decisions.data', 1)->where('decisions.data.0.text', 'Evaluasi bulanan'));
        $this->get(route('decisions.index', ['from' => '2026-09-21']))
            ->assertInertia(fn (Assert $page) => $page->has('decisions.data', 0));

        $leader = User::factory()->create(['unit_id' => $otherUnit->id]);
        $leader->syncRoles(['pimpinan']);
        $this->actingAs($leader)->get(route('decisions.index'))
            ->assertInertia(fn (Assert $page) => $page->has('decisions.data', 3)->has('units', 2));
        $this->get(route('decisions.index', ['unit_id' => $this->unit->id]))
            ->assertInertia(fn (Assert $page) => $page->has('decisions.data', 2));
    }

    public function test_backfill_command_extracts_decisions_for_old_meetings(): void
    {
        $this->meeting(['status' => 'Selesai Diproses', 'summary' => '<p>Anggaran disepakati</p>']);

        $this->artisan('meetings:extract-decisions')->assertSuccessful();

        $this->assertSame(2, MeetingDecision::count());
    }

    public function test_parser_handles_llm_quirks(): void
    {
        $service = app(DecisionService::class);

        $this->assertNull($service->parse('Maaf, saya tidak bisa.'));
        $this->assertSame([], $service->parse('[]'));
        $this->assertSame(
            [['text' => 'A disepakati', 'follow_up' => 2], ['text' => 'B ditunda', 'follow_up' => null]],
            $service->parse("Berikut:\n[{\"keputusan\": \" A disepakati \", \"tindak_lanjut\": \"2\"}, \"B ditunda\", {\"keputusan\": \"\"}, {\"keputusan\": \"a disepakati\"}]"),
        );
    }
}
