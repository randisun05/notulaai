<?php

namespace Tests\Feature\Memory;

use App\Models\AiRequestLog;
use App\Models\Meeting;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrossMeetingQaTest extends TestCase
{
    use RefreshDatabase;

    private Unit $roads;

    private Unit $water;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        MemoryFakeText::$prompts = [];
        MemoryFakeText::$answer = 'Anggaran jembatan ditetapkan Rp4,2 miliar pada rapat 1 September 2026 [1].';
        Config::set('ai.providers.memory_text', ['driver' => MemoryFakeText::class, 'model' => 'fake']);
        Config::set('ai.fallbacks', ['text' => [], 'transcription' => [], 'ocr' => []]);
        Setting::current()->update(['ai_text_provider' => 'memory_text']);

        $this->roads = Unit::create(['name' => 'Bidang Jalan']);
        $this->water = Unit::create(['name' => 'Bidang Air']);
        $this->member = User::factory()->create(['unit_id' => $this->roads->id]);
        $this->member->syncRoles(['user']);

        $bridge = $this->meeting($this->roads, 'Rapat Jembatan', '2026-09-01 09:00', '<p>Anggaran jembatan disepakati Rp4,2 miliar.</p>',
            "[00:00:00]\nBudi: pembukaan.\n[00:40:00]\nSiti: anggaran jembatan 4,2 miliar kita sepakati.");
        $bridge->decisions()->create(['unit_id' => $this->roads->id, 'text' => 'Anggaran jembatan Rp4,2 miliar', 'source' => 'notula']);
        $bridge->actionItems()->create(['title' => 'Susun RAB jembatan', 'assignee_name' => 'Budi', 'order' => 0]);
        $this->meeting($this->roads, 'Rapat Kepegawaian', '2026-09-10 09:00', '<p>Cuti bersama dibahas.</p>', 'Cuti bersama.');
        $this->meeting($this->water, 'Rapat Pipa', '2026-09-05 09:00', '<p>Anggaran pipa air Rp1 miliar.</p>', 'Anggaran pipa.');
        $this->meeting($this->roads, 'Rapat Belum Diproses', '2026-09-20 09:00', null, null, 'Dijadwalkan');
    }

    private function meeting(Unit $unit, string $title, string $date, ?string $summary, ?string $transcript, string $status = 'Selesai Diproses'): Meeting
    {
        return Meeting::create(['user_id' => $this->member->id, 'unit_id' => $unit->id, 'title' => $title, 'date' => $date,
            'summary' => $summary, 'transcript' => $transcript, 'status' => $status]);
    }

    public function test_answer_cites_the_relevant_meetings_of_the_users_unit_only(): void
    {
        $this->actingAs($this->member)->get(route('ask.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Ask/Index')->where('units', []));

        $response = $this->postJson(route('ask.store'), ['question' => 'Berapa anggaran jembatan yang disepakati?'])->assertOk();

        $this->assertSame('Anggaran jembatan ditetapkan Rp4,2 miliar pada rapat 1 September 2026 [1].', $response->json('answer'));
        $this->assertSame('Rapat Jembatan', $response->json('sources.0.title'));
        $this->assertTrue($response->json('sources.0.cited'));
        $this->assertSame('Bidang Jalan', $response->json('sources.0.unit'));
        $this->assertNotContains('Rapat Pipa', array_column($response->json('sources'), 'title'), 'Rapat unit lain tidak pernah jadi sumber.');
        $this->assertNotContains('Rapat Belum Diproses', array_column($response->json('sources'), 'title'));

        $prompt = MemoryFakeText::$prompts[0];
        $this->assertStringContainsString('=== [1] Rapat Jembatan — Selasa, 1 September 2026 — Bidang Jalan ===', $prompt);
        $this->assertStringContainsString('- Anggaran jembatan Rp4,2 miliar (tindak lanjut: Tanpa tindak lanjut)', $prompt);
        $this->assertStringContainsString('- Susun RAB jembatan (PIC: Budi) [status: belum jadi Task]', $prompt);
        $this->assertStringContainsString('Siti: anggaran jembatan 4,2 miliar', $prompt);
        $this->assertStringNotContainsString('Rapat Pipa', $prompt);

        $this->assertSame(1, AiRequestLog::where('type', 'cross_meeting_qa')->where('user_id', $this->member->id)->count());
    }

    public function test_pimpinan_asks_across_units_and_can_narrow_by_unit_and_date(): void
    {
        $leader = User::factory()->create(['unit_id' => $this->water->id]);
        $leader->syncRoles(['pimpinan']);

        $this->actingAs($leader)->get(route('ask.index'))->assertInertia(fn (Assert $page) => $page->has('units', 2));

        $titles = fn ($response) => array_column($response->json('sources'), 'title');

        $all = $this->postJson(route('ask.store'), ['question' => 'Rekap semua anggaran'])->assertOk();
        $this->assertContains('Rapat Pipa', $titles($all));
        $this->assertContains('Rapat Jembatan', $titles($all));

        $water = $this->postJson(route('ask.store'), ['question' => 'Rekap semua anggaran', 'unit_id' => $this->water->id])->assertOk();
        $this->assertSame(['Rapat Pipa'], $titles($water));

        $dated = $this->postJson(route('ask.store'), ['question' => 'Rekap semua anggaran', 'from' => '2026-09-02', 'to' => '2026-09-05'])->assertOk();
        $this->assertSame(['Rapat Pipa'], $titles($dated));
    }

    public function test_unit_filter_is_ignored_for_regular_users(): void
    {
        $response = $this->actingAs($this->member)->postJson(route('ask.store'), ['question' => 'anggaran pipa', 'unit_id' => $this->water->id])->assertOk();

        $this->assertNotContains('Rapat Pipa', array_column($response->json('sources'), 'title'));
    }

    public function test_no_processed_meetings_means_no_ai_call(): void
    {
        $stranger = User::factory()->create(['unit_id' => Unit::create(['name' => 'Kosong'])->id]);
        $stranger->syncRoles(['user']);

        $this->actingAs($stranger)->postJson(route('ask.store'), ['question' => 'Apa keputusan terbaru?'])
            ->assertOk()->assertJsonPath('sources', []);
        $this->assertSame([], MemoryFakeText::$prompts);
    }

    public function test_question_is_required_and_route_is_rate_limited(): void
    {
        $this->actingAs($this->member)->postJson(route('ask.store'), [])->assertJsonValidationErrors('question');
        $this->assertContains('throttle:ai', app('router')->getRoutes()->getByName('ask.store')->middleware());
    }
}
