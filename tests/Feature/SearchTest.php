<?php

namespace Tests\Feature;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Unit $otherUnit;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unit = Unit::factory()->create();
        $this->otherUnit = Unit::factory()->create();
        $this->user = User::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Budi']);
        $this->user->syncRoles(['user']);
    }

    private function meeting(Unit $unit, array $attributes = []): Meeting
    {
        return Meeting::create(array_merge([
            'user_id' => $this->user->id, 'unit_id' => $unit->id, 'title' => 'Rapat',
            'date' => '2026-10-01', 'agenda' => 'Agenda umum', 'attendees' => '-', 'status' => 'Selesai Diproses',
        ], $attributes));
    }

    private function quick(string $q, ?User $as = null): array
    {
        return $this->actingAs($as ?? $this->user)->getJson(route('search.quick', ['q' => $q]))->assertOk()->json();
    }

    public function test_finds_meetings_tasks_and_comments_in_the_users_unit(): void
    {
        $meeting = $this->meeting($this->unit, ['title' => 'Rapat Anggaran Q4']);
        Task::create(['unit_id' => $this->unit->id, 'title' => 'Revisi anggaran', 'status' => 'Todo']);
        ForumComment::create(['meeting_id' => $meeting->id, 'user_id' => $this->user->id, 'body' => 'Anggaran marketing perlu dipotong']);

        $results = $this->quick('anggaran');

        $this->assertSame(['Rapat Anggaran Q4'], array_column($results['meetings'], 'title'));
        $this->assertSame(['Revisi anggaran'], array_column($results['tasks'], 'title'));
        $this->assertSame('Rapat Anggaran Q4', $results['comments'][0]['title']);
        $this->assertSame('Budi', $results['comments'][0]['subtitle']);
        $this->assertStringEndsWith("/meetings/{$meeting->id}#forum", $results['comments'][0]['url']);
    }

    public function test_never_returns_another_units_records(): void
    {
        $foreign = $this->meeting($this->otherUnit, ['title' => 'Rahasia merger']);
        Task::create(['unit_id' => $this->otherUnit->id, 'title' => 'Rahasia task', 'status' => 'Todo']);
        ForumComment::create(['meeting_id' => $foreign->id, 'user_id' => $this->user->id, 'body' => 'rahasia komentar']);
        // A task whose description matches must not leak through an OR either.
        Task::create(['unit_id' => $this->otherUnit->id, 'title' => 'Lain', 'description' => 'rahasia', 'status' => 'Todo']);

        $results = $this->quick('rahasia');

        $this->assertSame(['meetings' => [], 'tasks' => [], 'comments' => []], $results);
    }

    public function test_superadmin_searches_every_unit(): void
    {
        $superadmin = User::factory()->create(['unit_id' => $this->unit->id]);
        $superadmin->syncRoles(['superadmin']);
        $this->meeting($this->unit, ['title' => 'Vendor A']);
        $this->meeting($this->otherUnit, ['title' => 'Vendor B']);

        $this->assertCount(2, $this->quick('vendor', $superadmin)['meetings']);
    }

    public function test_short_queries_return_nothing_and_snippets_are_plain_text(): void
    {
        $this->meeting($this->unit, ['title' => 'Q', 'summary' => '<p>Keputusan: <strong>naikkan</strong> anggaran pelatihan.</p><script>x</script>']);

        $this->assertSame(['meetings' => [], 'tasks' => [], 'comments' => []], $this->quick('a'));

        $snippet = $this->quick('pelatihan')['meetings'][0]['snippet'];
        $this->assertStringContainsString('naikkan anggaran pelatihan', $snippet);
        $this->assertStringNotContainsString('<', $snippet);
    }

    public function test_quick_search_caps_each_group_and_the_page_shows_more(): void
    {
        foreach (range(1, 7) as $i) {
            Task::create(['unit_id' => $this->unit->id, 'title' => "Laporan {$i}", 'status' => 'Todo']);
        }

        $this->assertCount(5, $this->quick('laporan')['tasks']);

        $this->actingAs($this->user)->get(route('search.index', ['q' => 'laporan']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Search/Index')->where('q', 'laporan')->has('results.tasks', 7));
    }

    public function test_search_page_without_a_query_renders_empty(): void
    {
        $this->actingAs($this->user)->get(route('search.index'))
            ->assertInertia(fn ($page) => $page->where('q', null)->where('results', null));
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('search.quick', ['q' => 'abc']))->assertRedirect(route('login'));
    }
}
