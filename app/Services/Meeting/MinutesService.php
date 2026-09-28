<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use App\Models\MeetingMinutes;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
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
    ) {}

    /**
     * Susun (atau susun ulang) isi notulen dengan AI. Identitas rapat yang sudah
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
            $date = Carbon::parse($meeting->date);
            $minutes->fill([
                'time_range' => $date->format('H.i').' WIB – selesai',
                'minute_taker_id' => $user->id,
                'attendees' => $meeting->attendees,
                'agenda' => $this->plainText($meeting->agenda) ?: $meeting->title,
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

        $prompt = <<<PROMPT
        Susun isi NOTULEN RAPAT resmi instansi pemerintah dalam Bahasa Indonesia baku (ragam dinas: kalimat efektif,
        lugas, cenderung pasif, tanpa singkatan tidak baku, tanpa emoji). Hanya berdasarkan data di bawah; jangan
        mengarang nama, angka, atau keputusan yang tidak ada.

        Balas HANYA dengan satu objek JSON valid (tanpa markdown) dengan field:
        - "pembukaan": 1–3 kalimat tentang pembukaan rapat (siapa membuka, tujuan rapat) bila tersedia.
        - "pembahasan": array objek {"topik": judul singkat, "uraian": paragraf ringkas hasil pembahasan}, urut sesuai jalannya rapat.
        - "keputusan": array kalimat keputusan/kesimpulan rapat (tanpa penomoran).
        - "penutup": 1 kalimat penutup rapat bila tersedia.

        Judul rapat: {$meeting->title}
        Tanggal: {$meeting->date}

        Rangkuman rapat:
        {$this->plainText($meeting->summary)}

        Keputusan yang ditandai notulis saat rapat (wajib ada di "keputusan"):
        {$decisionMarkers}

        Tindak lanjut yang sudah tercatat (jangan diulang di "keputusan" kecuali memang keputusan):
        {$actionItems}
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

    private function plainText(?string $html): string
    {
        $text = preg_replace(['/<\/(p|li|h\d|div)>/i', '/<br\s*\/?>/i'], "\n", (string) $html);

        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($text))));
    }
}
