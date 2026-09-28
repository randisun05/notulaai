<?php

namespace Tests\Feature\Meeting;

use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\MeetingMinutes;
use App\Models\MeetingSegment;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\AI\Contracts\TranscriptionProvider;
use App\Services\AI\DTO\AiTranscriptionResult;
use App\Services\Meeting\MeetingProcessingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use ZipArchive;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $notulen;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->unit = Unit::factory()->create(['name' => 'Bagian Umum']);
        $this->notulen = $this->member($this->unit, 'Rina Notulen');
        $this->meeting = Meeting::create([
            'title' => 'Rapat Koordinasi', 'date' => '2026-10-05 09:00:00', 'status' => 'Dijadwalkan',
            'unit_id' => $this->unit->id, 'user_id' => $this->notulen->id,
        ]);
    }

    private function member(Unit $unit, string $name = 'Pegawai'): User
    {
        $user = User::factory()->create(['unit_id' => $unit->id, 'name' => $name]);
        $user->syncRoles(['user']);

        return $user;
    }

    private function token(): string
    {
        return $this->meeting->fresh()->attendanceToken();
    }

    public function test_meeting_page_shows_the_qr_link_and_the_list(): void
    {
        $this->actingAs($this->notulen)->get(route('meetings.show', $this->meeting))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attendance.url', route('attendance.check-in', $this->meeting->fresh()->attendance_token))
                ->where('attendance.open', true)
                ->has('attendance.list', 0));

        $this->assertSame(40, strlen($this->meeting->fresh()->attendance_token));
    }

    public function test_a_logged_in_employee_checks_in_once_even_from_another_unit(): void
    {
        $guestEmployee = $this->member(Unit::factory()->create(['name' => 'Biro Hukum']), 'Andi Wijaya');

        $this->actingAs($guestEmployee)->post(route('attendance.check-in.store', $this->token()))->assertSessionHas('success');
        $this->actingAs($guestEmployee)->post(route('attendance.check-in.store', $this->token()))->assertSessionHas('success');

        $this->assertDatabaseCount('meeting_attendances', 1);
        $this->assertDatabaseHas('meeting_attendances', ['user_id' => $guestEmployee->id, 'name' => 'Andi Wijaya', 'organization' => 'Biro Hukum', 'method' => 'qr']);
        // Hadir di rapat tidak memberi akses ke rapat unit lain.
        $this->actingAs($guestEmployee)->get(route('meetings.show', $this->meeting))->assertForbidden();
    }

    public function test_a_guest_without_an_account_checks_in_with_name_position_and_agency(): void
    {
        $this->get(route('attendance.check-in', $this->token()))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Attendance/CheckIn')->where('meeting.title', 'Rapat Koordinasi')->where('user', null));
        $this->assertSame(route('attendance.check-in', $this->token()), session('url.intended'), 'Setelah login kembali ke halaman hadir.');

        $this->post(route('attendance.check-in.store', $this->token()), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('attendance.check-in.store', $this->token()), [
            'name' => 'Dr.  Siti Aminah', 'position' => 'Kepala Dinas', 'organization' => 'Dinas Pendidikan',
        ])->assertSessionHas('success');
        // Memindai ulang dengan nama yang sama (beda huruf besar) tidak menggandakan.
        $this->post(route('attendance.check-in.store', $this->token()), ['name' => 'dr. siti aminah', 'position' => 'Kepala Dinas Pendidikan']);

        $this->assertDatabaseCount('meeting_attendances', 1);
        $this->assertDatabaseHas('meeting_attendances', ['name' => 'Dr. Siti Aminah', 'position' => 'Kepala Dinas Pendidikan', 'organization' => 'Dinas Pendidikan', 'user_id' => null]);
    }

    public function test_unknown_or_replaced_tokens_are_not_found(): void
    {
        $this->get(route('attendance.check-in', 'bukan-token'))->assertNotFound();

        $old = $this->token();
        $this->actingAs($this->notulen)->post(route('meetings.attendance.regenerate', $this->meeting))->assertSessionHas('success');

        auth()->logout();
        $this->get(route('attendance.check-in', $old))->assertNotFound();
        $this->get(route('attendance.check-in', $this->token()))->assertOk();
    }

    public function test_closed_attendance_refuses_qr_check_ins_but_allows_manual_additions(): void
    {
        $this->actingAs($this->notulen)->post(route('meetings.attendance.toggle', $this->meeting));
        $this->assertFalse($this->meeting->fresh()->isAttendanceOpen());
        auth()->logout();

        $this->post(route('attendance.check-in.store', $this->token()), ['name' => 'Terlambat'])->assertSessionHas('error');
        $this->assertDatabaseCount('meeting_attendances', 0);

        $this->actingAs($this->notulen)->post(route('meetings.attendance.store', $this->meeting), ['name' => 'Terlambat', 'organization' => 'Inspektorat'])->assertSessionHas('success');
        $this->assertDatabaseHas('meeting_attendances', ['name' => 'Terlambat', 'method' => 'manual']);
    }

    public function test_minute_taker_adds_and_removes_attendees_but_other_units_cannot(): void
    {
        $colleague = $this->member($this->unit, 'Budi Santoso');
        $this->actingAs($this->notulen)->post(route('meetings.attendance.store', $this->meeting), ['user_id' => $colleague->id]);
        $entry = MeetingAttendance::sole();
        $this->assertSame('Budi Santoso', $entry->name);
        $this->assertSame('Bagian Umum', $entry->organization);

        $outsider = $this->member(Unit::factory()->create());
        $this->actingAs($outsider)->post(route('meetings.attendance.store', $this->meeting), ['name' => 'X'])->assertForbidden();
        $this->actingAs($outsider)->delete(route('meetings.attendance.destroy', [$this->meeting, $entry]))->assertForbidden();

        $this->actingAs($this->notulen)->delete(route('meetings.attendance.destroy', [$this->meeting, $entry]))->assertSessionHas('success');
        $this->assertModelMissing($entry);
    }

    public function test_attendees_help_the_ai_name_speakers(): void
    {
        AttendanceContextTranscriber::$context = null;
        Storage::fake('local');
        Config::set('ai.providers.ctx_stt', ['driver' => AttendanceContextTranscriber::class]);
        Config::set('ai.fallbacks.transcription', []);
        Setting::current()->update(['ai_transcription_provider' => 'ctx_stt']);
        $this->meeting->attendances()->create(['name' => 'Siti Aminah', 'position' => 'Kepala Dinas', 'organization' => 'Dinas Pendidikan', 'method' => 'qr', 'checked_in_at' => now()]);
        $this->meeting->update(['status' => 'Memproses', 'processing_stage' => 'transcribing', 'processing_total_segments' => 2]);
        Storage::disk('local')->put('seg.mp3', 'x');
        $segment = MeetingSegment::create(['meeting_id' => $this->meeting->id, 'index' => 0, 'start_seconds' => 0, 'end_seconds' => 600, 'audio_path' => 'seg.mp3']);
        MeetingSegment::create(['meeting_id' => $this->meeting->id, 'index' => 1, 'start_seconds' => 600, 'end_seconds' => 1200]);

        app(MeetingProcessingService::class)->transcribeSegment($segment);

        $this->assertStringContainsString('Hadir menurut daftar hadir: Siti Aminah, Kepala Dinas, Dinas Pendidikan', AttendanceContextTranscriber::$context);
    }

    public function test_the_minutes_list_attendance_and_approval_closes_it(): void
    {
        Setting::current()->update(['company_name' => 'Dinas Contoh']);
        $this->meeting->update(['status' => 'Selesai Diproses', 'summary' => '<p>x</p>']);
        $this->meeting->attendances()->create(['name' => 'Siti Aminah', 'position' => 'Kepala Dinas', 'organization' => 'Dinas Pendidikan', 'method' => 'qr', 'checked_in_at' => '2026-10-05 08:52:00']);
        $chair = $this->member($this->unit, 'Pak Kabag');
        $minutes = MeetingMinutes::create([
            'meeting_id' => $this->meeting->id, 'minute_taker_id' => $this->notulen->id, 'chairperson_id' => $chair->id,
            'resume' => [['speaker' => null, 'text' => 'Rapat dibuka.', 'response' => null]], 'status' => 'diajukan', 'submitted_by' => $this->notulen->id,
        ]);

        $docx = $this->actingAs($this->notulen)->get(route('meetings.minutes.docx', $this->meeting))->assertOk();
        $zip = new ZipArchive;
        $zip->open($docx->baseResponse->getFile()->getPathname());
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        foreach (['DAFTAR HADIR', 'Siti Aminah', 'Kepala Dinas', 'Dinas Pendidikan', '08.52'] as $expected) {
            $this->assertStringContainsString($expected, $xml);
        }
        $this->actingAs($this->notulen)->get(route('meetings.minutes.pdf', $this->meeting))->assertOk();

        $this->actingAs($chair)->post(route('meetings.minutes.approve', $this->meeting))->assertSessionHas('success');
        $this->assertFalse($this->meeting->fresh()->isAttendanceOpen(), 'Notula final → daftar hadir ditutup.');
        $this->assertSame('disahkan', $minutes->fresh()->status);
    }
}

class AttendanceContextTranscriber implements TranscriptionProvider
{
    public static ?string $context = null;

    public function transcribe(string $absoluteFilePath, string $fileName, ?string $language = null, ?string $context = null): AiTranscriptionResult
    {
        self::$context = $context;

        return new AiTranscriptionResult(text: 'Siti Aminah: selamat pagi.', provider: 'ctx_stt');
    }
}
