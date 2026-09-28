<?php

namespace Tests\Feature\Meeting;

use App\Models\Meeting;
use App\Models\MeetingMinutes;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use ZipArchive;

class MeetingMinutesTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $notulis;

    private User $kabag;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        MinutesFakeText::$prompts = [];
        Config::set('ai.providers.minutes_text', ['driver' => MinutesFakeText::class, 'model' => 'fake']);
        Config::set('ai.fallbacks', ['text' => [], 'transcription' => [], 'ocr' => []]);
        Setting::current()->update(['ai_text_provider' => 'minutes_text', 'company_name' => 'Dinas Pekerjaan Umum', 'company_address' => 'Jl. Merdeka No. 1']);

        $this->unit = Unit::factory()->create();
        $this->notulis = $this->member('user', 'Rina Notulis');
        $this->kabag = $this->member('user', 'Ir. Bambang, M.T.');
        $this->meeting = Meeting::create([
            'title' => 'Rapat Koordinasi Jembatan', 'date' => '2026-10-05 09:00:00', 'status' => 'Selesai Diproses',
            'unit_id' => $this->unit->id, 'user_id' => $this->notulis->id, 'attendees' => "Budi Santoso\nSiti Aminah",
            'agenda' => '<p>Pembahasan anggaran jembatan</p>', 'summary' => '<h3>Ringkasan</h3><ul><li>Anggaran 4,2 M disepakati</li></ul>',
            'transcript' => 'Budi: anggaran 4,2 miliar kita sepakati.',
        ]);
        $this->meeting->actionItems()->create(['title' => 'Susun RAB final', 'assignee_name' => 'Budi Santoso', 'deadline' => '2026-10-20', 'order' => 0]);
        $this->meeting->markers()->create(['type' => 'keputusan', 'at_seconds' => 125, 'note' => 'Anggaran 4,2 M']);
    }

    private function member(string $role, string $name = 'Pegawai'): User
    {
        $user = User::factory()->create(['unit_id' => $this->unit->id, 'name' => $name]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function drafted(array $attributes = []): MeetingMinutes
    {
        $this->actingAs($this->notulis)->post(route('meetings.minutes.generate', $this->meeting))->assertSessionHas('success');
        $minutes = $this->meeting->fresh()->minutes;
        $minutes->update($attributes);

        return $minutes;
    }

    public function test_ai_drafts_the_minutes_from_the_processed_meeting(): void
    {
        $minutes = $this->drafted();

        $this->assertSame('draf', $minutes->status);
        $this->assertSame('Rapat dibuka oleh Ir. Bambang pukul 09.00 WIB.', $minutes->opening);
        $this->assertSame([['topic' => 'Anggaran jembatan', 'notes' => 'Anggaran sebesar Rp4,2 miliar dibahas.']], $minutes->discussion);
        $this->assertSame(['Anggaran pembangunan jembatan ditetapkan sebesar Rp4,2 miliar.'], $minutes->decisions);
        // Identitas diisi dari data rapat.
        $this->assertSame("Budi Santoso\nSiti Aminah", $minutes->attendees);
        $this->assertSame('Pembahasan anggaran jembatan', $minutes->agenda);
        $this->assertSame($this->notulis->id, $minutes->minute_taker_id);
        $this->assertSame('09.00 WIB – selesai', $minutes->time_range);

        $prompt = MinutesFakeText::$prompts[0];
        $this->assertStringContainsString('Anggaran 4,2 M disepakati', $prompt, 'Rangkuman ikut dikirim.');
        $this->assertStringContainsString('[00:02:05] Keputusan: Anggaran 4,2 M', $prompt, 'Penanda keputusan wajib ada.');
        $this->assertStringContainsString('Susun RAB final (PIC: Budi Santoso) (tenggat 2026-10-20)', $prompt);
    }

    public function test_redrafting_keeps_identity_fields_filled_by_the_minute_taker(): void
    {
        $this->drafted(['number' => '005/12/PU/2026', 'location' => 'Ruang Rapat Lt. 2', 'opening' => 'lama']);

        $this->actingAs($this->notulis)->post(route('meetings.minutes.generate', $this->meeting));

        $minutes = $this->meeting->fresh()->minutes;
        $this->assertSame('005/12/PU/2026', $minutes->number);
        $this->assertSame('Ruang Rapat Lt. 2', $minutes->location);
        $this->assertSame('Rapat dibuka oleh Ir. Bambang pukul 09.00 WIB.', $minutes->opening);
    }

    public function test_minutes_need_a_processed_meeting(): void
    {
        $this->meeting->update(['status' => 'Dijadwalkan']);

        $this->actingAs($this->notulis)->post(route('meetings.minutes.generate', $this->meeting))->assertSessionHas('error');
        $this->assertNull($this->meeting->fresh()->minutes);
    }

    public function test_minute_taker_edits_and_submits_then_the_chairperson_approves(): void
    {
        $this->drafted();

        $this->actingAs($this->notulis)->put(route('meetings.minutes.update', $this->meeting), [
            'number' => '005/12/PU/2026',
            'location' => 'Ruang Rapat Lt. 2',
            'chairperson_id' => $this->kabag->id,
            'chairperson_title' => 'Kepala Bidang Jalan dan Jembatan',
            'minute_taker_id' => $this->notulis->id,
            'discussion' => [['topic' => 'Anggaran', 'notes' => 'Dibahas.'], ['topic' => '', 'notes' => '']],
            'decisions' => ['Disepakati 4,2 M', ''],
        ])->assertSessionHasNoErrors();

        $minutes = $this->meeting->fresh()->minutes;
        $this->assertSame([['topic' => 'Anggaran', 'notes' => 'Dibahas.']], $minutes->discussion, 'Baris kosong dibuang.');
        $this->assertSame(['Disepakati 4,2 M'], $minutes->decisions);

        $this->actingAs($this->notulis)->post(route('meetings.minutes.submit', $this->meeting))->assertSessionHas('success');
        $this->assertSame('diajukan', $minutes->fresh()->status);

        // Terkunci selama menunggu pengesahan.
        $this->actingAs($this->notulis)->put(route('meetings.minutes.update', $this->meeting), ['number' => 'x'])->assertStatus(409);

        $this->actingAs($this->kabag)->get(route('meetings.minutes.edit', $this->meeting))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Meetings/Minutes')->where('canApprove', true)->where('canEdit', false));

        $this->actingAs($this->kabag)->post(route('meetings.minutes.approve', $this->meeting))->assertSessionHas('success');
        $minutes->refresh();
        $this->assertSame('disahkan', $minutes->status);
        $this->assertSame($this->kabag->id, $minutes->approved_by);
        $this->assertDatabaseHas('activities', ['meeting_id' => $this->meeting->id, 'type' => 'minutes.approved']);

        // Setelah disahkan tidak bisa disusun ulang.
        $this->actingAs($this->notulis)->post(route('meetings.minutes.generate', $this->meeting))->assertSessionHas('error');
    }

    public function test_submitting_requires_a_chairperson(): void
    {
        $this->drafted();

        $this->actingAs($this->notulis)->post(route('meetings.minutes.submit', $this->meeting))->assertSessionHas('error');
        $this->assertSame('draf', $this->meeting->fresh()->minutes->status);
    }

    public function test_only_the_chairperson_may_approve_never_the_submitter_or_a_colleague(): void
    {
        $admin = $this->member('admin');
        $this->drafted(['chairperson_id' => $this->kabag->id, 'status' => 'diajukan', 'submitted_by' => $this->notulis->id]);

        $this->actingAs($this->notulis)->post(route('meetings.minutes.approve', $this->meeting))->assertForbidden();
        $this->actingAs($this->member('user'))->post(route('meetings.minutes.approve', $this->meeting))->assertForbidden();
        // Pimpinan adalah pengguna aplikasi → admin unit pun tidak mengambil alih pengesahannya.
        $this->actingAs($admin)->post(route('meetings.minutes.approve', $this->meeting))->assertForbidden();
    }

    public function test_an_external_chairperson_means_the_unit_admin_approves(): void
    {
        $admin = $this->member('admin');
        $this->drafted(['chairperson_name' => 'Kepala Dinas (tamu)', 'status' => 'diajukan', 'submitted_by' => $this->notulis->id]);

        $this->actingAs($admin)->post(route('meetings.minutes.approve', $this->meeting))->assertSessionHas('success');
        $this->assertSame('disahkan', $this->meeting->fresh()->minutes->status);
    }

    public function test_returned_minutes_carry_the_note_and_become_editable_again(): void
    {
        $this->drafted(['chairperson_id' => $this->kabag->id, 'status' => 'diajukan', 'submitted_by' => $this->notulis->id]);

        $this->actingAs($this->kabag)->post(route('meetings.minutes.return', $this->meeting), [])->assertSessionHasErrors('note');
        $this->actingAs($this->kabag)->post(route('meetings.minutes.return', $this->meeting), ['note' => 'Nomor belum diisi'])->assertSessionHas('success');

        $minutes = $this->meeting->fresh()->minutes;
        $this->assertSame('dikembalikan', $minutes->status);
        $this->assertSame('Nomor belum diisi', $minutes->return_note);
        $this->actingAs($this->notulis)->put(route('meetings.minutes.update', $this->meeting), ['number' => '005/12/PU/2026'])->assertSessionHasNoErrors();
    }

    public function test_chairperson_must_belong_to_the_meeting_unit(): void
    {
        $this->drafted();
        $outsider = User::factory()->create(['unit_id' => Unit::factory()->create()->id]);

        $this->actingAs($this->notulis)->put(route('meetings.minutes.update', $this->meeting), ['chairperson_id' => $outsider->id])
            ->assertSessionHasErrors('chairperson_id');
    }

    public function test_pdf_and_word_exports(): void
    {
        $this->drafted(['number' => '005/12/PU/2026', 'chairperson_id' => $this->kabag->id]);

        $pdf = $this->actingAs($this->notulis)->get(route('meetings.minutes.pdf', $this->meeting));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('(DRAF).pdf', $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $docx = $this->actingAs($this->notulis)->get(route('meetings.minutes.docx', $this->meeting));
        $docx->assertOk();
        $file = $docx->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file) === true, 'File .docx adalah zip yang valid.');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        foreach (['NOTULEN RAPAT', '005/12/PU/2026', 'DINAS PEKERJAAN UMUM', 'Anggaran pembangunan jembatan ditetapkan', 'Susun RAB final', 'Ir. Bambang, M.T.', 'Rina Notulis'] as $expected) {
            $this->assertStringContainsString(htmlspecialchars($expected, ENT_XML1), $xml, "Word berisi: {$expected}");
        }
    }

    public function test_users_from_another_unit_cannot_see_the_minutes(): void
    {
        $this->drafted();
        $outsider = User::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        $outsider->syncRoles(['user']);

        $this->actingAs($outsider)->get(route('meetings.minutes.edit', $this->meeting))->assertForbidden();
        $this->actingAs($outsider)->get(route('meetings.minutes.pdf', $this->meeting))->assertForbidden();
    }
}

class MinutesFakeText implements TextGenerationProvider
{
    /** @var list<string> */
    public static array $prompts = [];

    public function __construct(private readonly ?string $model = null) {}

    public function generate(string $prompt): AiTextResult
    {
        self::$prompts[] = $prompt;

        return new AiTextResult(content: "Berikut notulennya:\n```json\n".json_encode([
            'pembukaan' => 'Rapat dibuka oleh Ir. Bambang pukul 09.00 WIB.',
            'pembahasan' => [['topik' => 'Anggaran jembatan', 'uraian' => 'Anggaran sebesar Rp4,2 miliar dibahas.']],
            'keputusan' => ['Anggaran pembangunan jembatan ditetapkan sebesar Rp4,2 miliar.'],
            'penutup' => 'Rapat ditutup pukul 11.30 WIB.',
        ])."\n```", provider: 'minutes_text', model: 'fake');
    }
}
