<?php

namespace Tests\Feature\Meeting;

use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingSearchTest extends TestCase
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
        $this->user = User::factory()->create(['unit_id' => $this->unit->id]);
        $this->user->syncRoles(['user']);
    }

    private function meeting(Unit $unit, string $title, array $attributes = []): Meeting
    {
        return Meeting::create(array_merge([
            'user_id' => $this->user->id,
            'unit_id' => $unit->id,
            'title' => $title,
            'date' => now(),
            'agenda' => 'Agenda umum',
            'attendees' => 'Semua',
            'status' => 'Selesai Diproses',
        ], $attributes));
    }

    /**
     * InnoDB only indexes FULLTEXT rows on COMMIT, and RefreshDatabase never
     * commits, so on MySQL these assertions can't see the rows they create.
     * The FULLTEXT path was verified against MariaDB with committed rows;
     * CI and the default phpunit.xml run on SQLite, where the LIKE path runs.
     */
    private function skipOnFullTextDriver(): void
    {
        if (in_array(Meeting::query()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('InnoDB FULLTEXT does not see uncommitted rows inside RefreshDatabase transactions.');
        }
    }

    /** @return array<int, string> */
    private function searchTitles(User $user, string $search): array
    {
        $titles = [];

        $this->actingAs($user)
            ->get(route('meetings.index', ['search' => $search]))
            ->assertOk()
            ->assertInertia(function ($page) use (&$titles) {
                $titles = collect($page->toArray()['props']['meetings']['data'])->pluck('title')->all();
            });

        return $titles;
    }

    public function test_search_matches_title_agenda_transcript_and_summary(): void
    {
        $this->skipOnFullTextDriver();

        $this->meeting($this->unit, 'Rapat anggaran');
        $this->meeting($this->unit, 'Rapat A', ['agenda' => 'Bahas anggaran Q4']);
        $this->meeting($this->unit, 'Rapat B', ['transcript' => 'kita potong anggaran']);
        $this->meeting($this->unit, 'Rapat C', ['summary' => '<p>Anggaran disetujui</p>']);
        $this->meeting($this->unit, 'Rapat lain');

        $titles = $this->searchTitles($this->user, 'anggaran');

        sort($titles);
        $this->assertSame(['Rapat A', 'Rapat B', 'Rapat C', 'Rapat anggaran'], $titles);
    }

    public function test_search_never_returns_meetings_from_another_unit(): void
    {
        $this->skipOnFullTextDriver();

        $this->meeting($this->unit, 'Rapat kita', ['agenda' => 'rahasia merger']);
        $this->meeting($this->otherUnit, 'Rapat unit lain (agenda)', ['agenda' => 'rahasia merger']);
        $this->meeting($this->otherUnit, 'Rapat unit lain (transkrip)', ['transcript' => 'rahasia merger']);
        $this->meeting($this->otherUnit, 'Rapat unit lain (ringkasan)', ['summary' => 'rahasia merger']);

        $this->assertSame(['Rapat kita'], $this->searchTitles($this->user, 'rahasia'));
    }

    public function test_superadmin_search_spans_every_unit(): void
    {
        $this->skipOnFullTextDriver();

        $superadmin = User::factory()->create(['unit_id' => $this->unit->id]);
        $superadmin->syncRoles(['superadmin']);

        $this->meeting($this->unit, 'Rapat satu', ['agenda' => 'evaluasi vendor']);
        $this->meeting($this->otherUnit, 'Rapat dua', ['transcript' => 'evaluasi vendor']);

        $this->assertCount(2, $this->searchTitles($superadmin, 'vendor'));
    }

    public function test_like_wildcards_in_the_search_are_treated_literally(): void
    {
        $this->meeting($this->unit, 'Diskon 50% semua produk');
        $this->meeting($this->unit, 'Rapat biasa');

        $this->assertSame(['Diskon 50% semua produk'], $this->searchTitles($this->user, '50%'));
        $this->assertSame([], $this->searchTitles($this->user, '_'));
    }
}
