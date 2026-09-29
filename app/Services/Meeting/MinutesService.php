<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use App\Models\MeetingMinutes;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
use App\Support\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Throwable;

/**
 * Notulen resmi (format dinas): draf dari AI → disunting notulis → diajukan →
 * disahkan pimpinan rapat (atau dikembalikan).
 */
class MinutesService
{
    /** Transkrip sampai sepanjang ini ikut dikirim ke AI; lebih panjang cukup rangkumannya. */
    private const TRANSCRIPT_CHARS = 30000;

    public function __construct(
        private readonly AiManager $ai,
        private readonly AiRequestLogger $logger,
        private readonly MinutesDraftParser $parser,
        private readonly ActivityLogger $activityLogger,
        private readonly DecisionService $decisions,
        private readonly MeetingSeriesService $series,
    ) {}

    /**
     * Susun (atau susun ulang) resume notula dengan AI. Identitas rapat yang sudah
     * diisi notulis (nomor, tempat, pimpinan, ...) tidak ditimpa.
     */
    public function draft(Meeting $meeting, User $user): MeetingMinutes
    {
        if ($meeting->status !== 'Selesai Diproses') {
            throw new RuntimeException('Notulen resmi baru bisa disusun setelah notula rapat selesai diproses.');
        }

        $minutes = $meeting->minutes ?? new MeetingMinutes(['meeting_id' => $meeting->id, 'status' => MeetingMinutes::STATUS_DRAFT]);
        if ($minutes->exists && ! $minutes->isEditable()) {
            throw new RuntimeException('Notulen yang sudah diajukan atau disahkan tidak bisa disusun ulang.');
        }

        $content = $this->parser->parse($this->callAi($meeting, $user));

        if (! $minutes->exists) {
            $minutes->fill([
                'title' => $meeting->title,
                'time_range' => $this->timeRange($meeting),
                'minute_taker_id' => $user->id,
                'attendees' => $meeting->attendees ?: ($meeting->attendances()->exists() ? 'Sebagaimana daftar hadir terlampir' : null),
                'agenda' => $this->plainText($meeting->agenda) ?: null,
                'closing' => MeetingMinutes::DEFAULT_CLOSING,
            ]);
        }

        $minutes->fill($content)->save();

        $this->activityLogger->log($meeting, $user, 'minutes.drafted', "{$user->name} menyusun draf notulen resmi dengan AI.");

        return $minutes;
    }

    public function submit(MeetingMinutes $minutes, User $user): void
    {
        if (! $minutes->isEditable()) {
            throw new RuntimeException('Notulen ini sudah diajukan atau disahkan.');
        }
        if (! $minutes->chairpersonDisplayName() || ! $minutes->minute_taker_id) {
            throw new RuntimeException('Isi Pimpinan Rapat dan Notulis sebelum mengajukan pengesahan.');
        }

        $minutes->update([
            'status' => MeetingMinutes::STATUS_SUBMITTED,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'return_note' => null,
        ]);

        $approver = $minutes->chairperson->name ?? 'admin unit';
        $this->activityLogger->log($minutes->meeting, $user, 'minutes.submitted', "{$user->name} mengajukan notulen resmi untuk disahkan oleh {$approver}.");
    }

    public function approve(MeetingMinutes $minutes, User $user): void
    {
        $this->assertSubmitted($minutes);

        $minutes->update([
            'status' => MeetingMinutes::STATUS_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        // Notula final: daftar hadir yang tercetak di dalamnya tidak boleh bertambah lagi.
        $minutes->meeting->update(['attendance_closed_at' => $minutes->meeting->attendance_closed_at ?? now()]);
        // Kesimpulan resmi menggantikan hasil ekstraksi AI di register keputusan.
        $this->decisions->syncFromMinutes($minutes);

        $this->activityLogger->log($minutes->meeting, $user, 'minutes.approved', "{$user->name} mengesahkan notulen resmi rapat ini.");
    }

    public function return(MeetingMinutes $minutes, User $user, string $note): void
    {
        $this->assertSubmitted($minutes);

        $minutes->update(['status' => MeetingMinutes::STATUS_RETURNED, 'return_note' => $note]);

        $this->activityLogger->log($minutes->meeting, $user, 'minutes.returned', "{$user->name} mengembalikan notulen resmi untuk diperbaiki: {$note}");
    }

    private function assertSubmitted(MeetingMinutes $minutes): void
    {
        if ($minutes->status !== MeetingMinutes::STATUS_SUBMITTED) {
            throw new RuntimeException('Notulen ini tidak sedang menunggu pengesahan.');
        }
    }

    private function callAi(Meeting $meeting, User $user): string
    {
        $transcript = (string) $meeting->transcript;
        $decisionMarkers = $meeting->markers()->where('type', 'keputusan')->get()->map->describe()->implode("\n");
        $actionItems = $meeting->actionItems->map(fn ($item) => '- '.$item->title
            .($item->assignee_name ? " (PIC: {$item->assignee_name})" : '')
            .($item->deadline ? ' (tenggat '.$item->deadline->toDateString().')' : ''))->implode("\n");

        $attendance = $meeting->attendances()->get()->map(fn ($a) => '- '.$a->describe())->implode("\n");

        $prompt = <<<PROMPT
        Susun bagian RESUME dari NOTULA RAPAT resmi instansi pemerintah dalam Bahasa Indonesia baku ragam dinas
        (kalimat efektif, lugas, tanpa emoji). Hanya berdasarkan data di bawah; jangan mengarang nama, jabatan,
        angka, atau keputusan yang tidak ada.

        Resume ditulis sebagai poin-poin berurutan sesuai jalannya rapat, seperti notula dinas pada umumnya:
        1. Poin pembuka: "Pada hari ini dilaksanakan rapat ..." beserta pihak yang hadir bila disebut; siapa yang
           membuka rapat dan tujuan rapat; siapa yang memimpin pembahasan bila berbeda.
        2. Poin pembahasan: satu poin per masukan/pertanyaan/tanggapan peserta. Isi "pembicara" dengan nama dan
           unit/jabatannya sebagaimana disebut (mis. "Ika Meidyawati, Dit. Bangtarier" atau "Ibu Sesdep"), lalu
           "isi" dengan substansi masukan/pertanyaannya, dan "tanggapan" dengan jawaban/penjelasan yang diberikan
           (kosongkan bila tidak ada). Poin peralihan topik (mis. "Masuk ke pembahasan Pasal 15") boleh tanpa pembicara.
        Tulis nama persis seperti di data; bila nama tidak diketahui gunakan label pembicara yang ada.

        Balas HANYA dengan satu objek JSON valid (tanpa markdown):
        {"resume": [{"pembicara": string|null, "isi": string, "tanggapan": string|null}, ...],
         "kesimpulan": [string, ...]}
        "kesimpulan" berisi keputusan/kesimpulan rapat yang jelas disepakati (boleh array kosong).

        Judul rapat: {$meeting->title}
        Tanggal: {$meeting->date}

        Rangkuman rapat:
        {$this->plainText($meeting->summary)}

        Keputusan yang ditandai notulis saat rapat (wajib ada di "kesimpulan"):
        {$decisionMarkers}

        Tindak lanjut yang sudah tercatat (dicantumkan terpisah; jangan diulang di "kesimpulan" kecuali memang keputusan):
        {$actionItems}

        Daftar hadir (pakai nama & jabatan ini untuk menyebut pembicara bila cocok):
        {$attendance}

        {$this->series->promptContext($meeting)}
        PROMPT;

        if ($transcript !== '' && mb_strlen($transcript) <= self::TRANSCRIPT_CHARS) {
            $prompt .= "\n\nTranskrip:\n{$transcript}";
        }

        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('minutes', $provider, $model, $prompt, $e->getMessage(), $meeting, $user);
            throw new RuntimeException('Gagal menyusun draf notulen: '.$e->getMessage(), previous: $e);
        }

        $this->logger->logSuccess('minutes', $result->provider, $result->model, $prompt, $result->content, $result->promptTokens, $result->completionTokens, $result->durationMs, $meeting, $user);

        return $result->content;
    }

    /**
     * "09.00 – 11.40 WIB" kalau durasi rekaman diketahui, selain itu "09.00 WIB – selesai".
     */
    private function timeRange(Meeting $meeting): string
    {
        $start = Carbon::parse($meeting->date);
        $seconds = (float) $meeting->segments()->max('end_seconds');

        return $seconds > 0
            ? $start->format('H.i').' – '.$start->copy()->addSeconds((int) round($seconds))->format('H.i').' WIB'
            : $start->format('H.i').' WIB – selesai';
    }

    private function plainText(?string $html): string
    {
        return HtmlSanitizer::toText($html);
    }
}
