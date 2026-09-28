<?php

namespace Tests\Unit;

use App\Models\MeetingActionItem;
use App\Models\Task;
use Tests\TestCase;

/**
 * Kolom tanggal-saja (tenggat) harus sampai ke browser sebagai "YYYY-MM-DD".
 * Dengan app di WIB, serialisasi default Carbon menjadi "tanggal-1 T17:00Z"
 * (tengah malam WIB dalam UTC) — browser di zona lain menampilkan hari kemarin.
 */
class DateOnlySerializationTest extends TestCase
{
    public function test_deadlines_serialize_as_plain_dates(): void
    {
        $this->assertSame('2026-10-20', (new Task(['deadline' => '2026-10-20']))->toArray()['deadline']);
        $this->assertSame('2026-10-20', (new MeetingActionItem(['deadline' => '2026-10-20']))->toArray()['deadline']);
    }
}
