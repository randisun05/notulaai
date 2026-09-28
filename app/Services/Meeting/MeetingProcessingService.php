<?php

namespace App\Services\Meeting;

use App\Jobs\FinalizeMeetingNotula;
use App\Jobs\ProcessMeetingNotula;
use App\Jobs\TranscribeMeetingSegment;
use App\Mail\MeetingSummary;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingSegment;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
use App\Services\Audio\AudioSplitter;
use App\Services\Webhook\WebhookDispatcher;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MeetingProcessingService
{
    /**
     * Pemrosesan hanya boleh dimulai dari status ini. Memproses ulang rapat yang
     * sedang/sudah diproses menjalankan job ganda, menghapus action item (termasuk
     * yang sudah jadi Task), dan mengirim ulang email ke seluruh unit.
     */
    public const STARTABLE_STATUSES = ['Dijadwalkan', 'Gagal'];

    /** Audio & video (rekaman Zoom/Meet); ekstensi hasil tebakan MIME ikut didaftarkan. */
    public const AUDIO_EXTENSIONS = ['mp3', 'mpga', 'wav', 'm4a', 'm4b', 'mp4', 'mov', 'qt', 'webm', 'weba', 'ogg', 'oga', 'opus', 'aac', 'flac', 'mkv', 'mka'];

    private const TEXT_EXTENSIONS = ['txt', 'md'];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly AiManager $ai,
        private readonly AiRequestLogger $logger,
        private readonly ActionItemsParser $actionItemsParser,
        private readonly ActivityLogger $activityLogger,
        private readonly WebhookDispatcher $webhookDispatcher,
        private readonly AudioSplitter $audioSplitter,
    ) {}

    /**
     * Generate ulang action items dari transkrip yang sudah ada, tanpa perlu re-upload file.
     *
     * Anti-duplikat: (1) generateActionItems() selalu hapus dulu action items lama sebelum
     * membuat yang baru, jadi klik berkali-kali tidak pernah menumpuk; (2) generate ulang
     * DITOLAK kalau ada action item yang sudah dijadikan Task — kalau item itu ikut dihapus,
     * relasi Task ke action item sumbernya jadi terputus, dan AI berpotensi mengusulkan lagi
     * item yang sama sebagai entri baru yang belum ter-convert, yang lalu bisa "Jadikan Task"
     * lagi jadi Task kedua untuk pekerjaan yang sama.
     *
     * @return int jumlah action item hasil generate ulang
     */
    public function regenerateActionItems(Meeting $meeting): int
    {
        if (empty($meeting->transcript)) {
            throw new RuntimeException('Rapat ini belum memiliki transkrip untuk digenerate ulang.');
        }

        if ($meeting->actionItems()->where('converted_to_task', true)->exists()) {
            throw new RuntimeException('Tidak bisa generate ulang: sudah ada action item dari rapat ini yang dijadikan Task.');
        }

        $this->generateActionItems($meeting, $meeting->transcript);

        return $meeting->actionItems()->count();
    }

    public static function canStart(Meeting $meeting): bool
    {
        return in_array($meeting->status, self::STARTABLE_STATUSES, true);
    }

    /**
     * Titik masuk tunggal (web & API): simpan path sumber, tandai Memproses, antrekan job.
     */
    public function start(Meeting $meeting, string $sourceFilePath, User $user): void
    {
        $meeting->update([
            'source_file_path' => $sourceFilePath,
            'status' => 'Memproses',
            'processing_stage' => null,
            'processing_total_segments' => null,
            'processing_heartbeat_at' => now(),
        ]);

        $this->activityLogger->log($meeting, $user, 'meeting.processing_started', $user->name.' memulai proses pembuatan notula.');

        ProcessMeetingNotula::dispatch($meeting);
    }

    /**
     * Langkah pertama job ProcessMeetingNotula. Rekaman audio/video dipecah dan
     * ditranskrip per bagian oleh job terpisah (lanjut di transcribeSegment());
     * teks & gambar langsung diekstrak lalu difinalisasi di job ini juga.
     */
    public function process(Meeting $meeting): void
    {
        Log::info("Memulai pemrosesan untuk Rapat ID: {$meeting->id}");

        if ($this->isAudio($meeting->source_file_path)) {
            $this->startSegmentedTranscription($meeting);

            return;
        }

        $this->finalize($meeting, $this->extractTranscript($meeting));
    }

    /**
     * Rangkum transkrip lengkap, simpan, buat action items, lalu beri tahu unit.
     */
    public function finalize(Meeting $meeting, string $transcript): void
    {
        $this->heartbeat($meeting, 'summarizing');

        $summary = $this->summarize($meeting, $transcript);

        Log::info("Rangkuman berhasil dibuat untuk Rapat ID: {$meeting->id}");

        $meeting->update([
            'transcript' => $transcript,
            // AI diminta membalas HTML dan disimpan mentah untuk dirender (v-html /
            // {!! !!}); transkrip bisa berisi prompt injection, jadi sanitasi dulu.
            'summary' => HtmlSanitizer::clean($summary),
            'status' => 'Selesai Diproses',
            'processing_stage' => null,
        ]);
        $meeting->refresh();

        // Action items bersifat pelengkap: kalau AI gagal menghasilkan/mem-parse-nya,
        // notula tetap dianggap berhasil diproses (transkrip + rangkuman sudah aman).
        $this->generateActionItems($meeting, $transcript);

        $this->deleteSegmentAudio($meeting);

        $this->activityLogger->log($meeting, null, 'meeting.processed', 'AI berhasil membuat transkrip dan rangkuman notula.');
        $this->webhookDispatcher->dispatch('meeting.processed', $meeting, ['meeting_id' => $meeting->id, 'title' => $meeting->title]);

        $this->notifyUnit($meeting);
    }

    /**
     * Tandai Gagal — atomik, jadi beberapa job/watchdog yang gagal bersamaan
     * hanya mencatat satu aktivitas. Mengembalikan false kalau sudah tidak Memproses.
     */
    public function markFailed(Meeting $meeting, string $reason): bool
    {
        $updated = Meeting::whereKey($meeting->id)
            ->where('status', 'Memproses')
            ->update(['status' => 'Gagal', 'processing_stage' => null, 'updated_at' => now()]);

        if (! $updated) {
            return false;
        }

        $meeting->refresh();
        $this->activityLogger->log($meeting, null, 'meeting.failed', 'Pemrosesan notula gagal: '.$reason);

        return true;
    }

    private function isAudio(?string $path): bool
    {
        return in_array(strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)), self::AUDIO_EXTENSIONS, true);
    }

    private function heartbeat(Meeting $meeting, ?string $stage = null): void
    {
        $meeting->forceFill(array_filter([
            'processing_heartbeat_at' => now(),
            'processing_stage' => $stage,
        ]))->save();
    }

    private function segmentDirectory(Meeting $meeting): string
    {
        return "meeting_segments/{$meeting->id}";
    }

    private function deleteSegmentAudio(Meeting $meeting): void
    {
        Storage::disk('local')->deleteDirectory($this->segmentDirectory($meeting));
    }

    /**
     * Pecah rekaman jadi potongan audio (disk privat `local`) dan antrekan satu
     * job transkripsi per potongan.
     */
    private function startSegmentedTranscription(Meeting $meeting): void
    {
        $filePath = $meeting->source_file_path;
        if (! $filePath || ! Storage::disk('public')->exists($filePath)) {
            throw new RuntimeException("File sumber tidak ditemukan di path: {$filePath}");
        }

        // Proses ulang (setelah Gagal) mulai dari nol.
        $meeting->segments()->delete();
        $this->deleteSegmentAudio($meeting);

        $directory = $this->segmentDirectory($meeting);
        $parts = $this->audioSplitter->split(
            Storage::disk('public')->path($filePath),
            Storage::disk('local')->path($directory),
        );

        $segments = collect($parts)->values()->map(fn (array $part, int $index) => MeetingSegment::create([
            'meeting_id' => $meeting->id,
            'index' => $index,
            'start_seconds' => $part['start'],
            'end_seconds' => $part['end'],
            'audio_path' => $directory.'/'.basename($part['path']),
        ]));

        $meeting->forceFill([
            'processing_stage' => 'transcribing',
            'processing_total_segments' => $segments->count(),
            'processing_heartbeat_at' => now(),
        ])->save();

        Log::info("Rapat ID {$meeting->id} dipecah menjadi {$segments->count()} bagian untuk ditranskrip.");

        foreach ($segments as $segment) {
            TranscribeMeetingSegment::dispatch($segment);
        }
    }

    /**
     * Transkrip satu potongan (dipanggil job TranscribeMeetingSegment). Potongan
     * terakhir yang selesai memicu FinalizeMeetingNotula.
     */
    public function transcribeSegment(MeetingSegment $segment): void
    {
        $meeting = $segment->meeting;

        // Rapat sudah Gagal (bagian lain gagal / watchdog) atau dihapus: berhenti.
        if (! $meeting || $meeting->status !== 'Memproses' || $segment->status === MeetingSegment::STATUS_DONE) {
            return;
        }

        $this->heartbeat($meeting);

        $provider = $this->ai->activeTranscriptionProvider();
        $fileName = basename((string) $segment->audio_path);

        try {
            $result = $this->ai->transcription()->transcribe(
                absoluteFilePath: Storage::disk('local')->path((string) $segment->audio_path),
                fileName: $fileName,
                language: 'Indonesian',
            );
        } catch (Throwable $e) {
            $this->logger->logFailure('transcription', $provider, null, $fileName, $e->getMessage(), $meeting, $meeting->creator);
            throw $e;
        }

        $this->logger->logSuccess('transcription', $result->provider, null, $fileName, $result->text, null, null, $result->durationMs, $meeting, $meeting->creator);

        $segment->update(['status' => MeetingSegment::STATUS_DONE, 'text' => $result->text, 'error' => null]);
        Storage::disk('local')->delete((string) $segment->audio_path);

        $this->heartbeat($meeting);

        // Klaim atomik: kalau dua bagian terakhir selesai bersamaan, hanya satu
        // yang berhasil memindahkan stage ke summarizing dan memicu finalisasi.
        $claimed = Meeting::whereKey($meeting->id)
            ->where('status', 'Memproses')
            ->where('processing_stage', 'transcribing')
            ->whereDoesntHave('segments', fn ($q) => $q->where('status', '!=', MeetingSegment::STATUS_DONE))
            ->update(['processing_stage' => 'summarizing', 'processing_heartbeat_at' => now()]);

        if ($claimed) {
            FinalizeMeetingNotula::dispatch($meeting);
        }
    }

    /**
     * Gabungkan semua potongan jadi satu transkrip bertanda waktu, lalu finalisasi.
     */
    public function finalizeFromSegments(Meeting $meeting): void
    {
        if ($meeting->status !== 'Memproses') {
            return;
        }

        $transcript = $meeting->segments()->get()
            ->filter(fn (MeetingSegment $segment) => trim((string) $segment->text) !== '')
            ->map(fn (MeetingSegment $segment) => $segment->startLabel()."\n".trim((string) $segment->text))
            ->implode("\n\n");

        if ($transcript === '') {
            throw new RuntimeException('Tidak ada ucapan yang terdeteksi di rekaman.');
        }

        Log::info("Transkrip {$meeting->segments()->count()} bagian digabung untuk Rapat ID: {$meeting->id}");

        $this->finalize($meeting, $transcript);
    }

    private function extractTranscript(Meeting $meeting): string
    {
        $filePath = $meeting->source_file_path;
        if (! $filePath || ! Storage::disk('public')->exists($filePath)) {
            throw new RuntimeException("File sumber tidak ditemukan di path: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, self::TEXT_EXTENSIONS)) {
            $transcript = Storage::disk('public')->get($filePath);

            if (empty(trim($transcript))) {
                throw new RuntimeException('Transkrip kosong, tidak bisa membuat rangkuman.');
            }

            return $transcript;
        }

        if (in_array($extension, self::IMAGE_EXTENSIONS)) {
            $provider = $this->ai->activeOcrProvider();
            $fileName = basename($filePath);

            try {
                $result = $this->ai->ocr()->extractText(
                    absoluteFilePath: Storage::disk('public')->path($filePath),
                    fileName: $fileName,
                );
            } catch (Throwable $e) {
                $this->logger->logFailure('ocr', $provider, null, $fileName, $e->getMessage(), $meeting, $meeting->creator);
                throw $e;
            }

            if (empty(trim($result->content))) {
                throw new RuntimeException('Tidak ada teks yang terbaca dari gambar tersebut.');
            }

            $this->logger->logSuccess('ocr', $result->provider, $result->model, $fileName, $result->content, null, null, $result->durationMs, $meeting, $meeting->creator);

            return $result->content;
        }

        throw new RuntimeException("Tipe file tidak didukung: {$extension}");
    }

    private function summarize(Meeting $meeting, string $transcript): string
    {
        $prompt = 'You are a helpful assistant that summarizes meeting transcripts. Create a summary in well-structured HTML format. '
            .'Use headings (<h3>), unordered lists (<ul><li>) for key points, and bold tags (<b>) to highlight action items or names. '
            .'Here is the transcript: '.$transcript;

        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('text', $provider, $model, $prompt, $e->getMessage(), $meeting, $meeting->creator);
            throw $e;
        }

        $this->logger->logSuccess('text', $result->provider, $result->model, $prompt, $result->content, $result->promptTokens, $result->completionTokens, $result->durationMs, $meeting, $meeting->creator);

        return $result->content;
    }

    private function generateActionItems(Meeting $meeting, string $transcript): void
    {
        $prompt = <<<PROMPT
        Dari transkrip rapat berikut, ekstrak daftar action item (tindak lanjut) yang disebutkan.
        Balas HANYA dengan JSON array yang valid, tanpa teks lain dan tanpa markdown code fence.
        Setiap elemen array berbentuk objek dengan field:
        - "title": deskripsi singkat tindakan yang harus dilakukan (wajib diisi)
        - "assignee_name": nama orang/tim penanggung jawab jika disebutkan, atau null jika tidak ada
        - "deadline": tanggal tenggat dalam format YYYY-MM-DD jika disebutkan, atau null jika tidak ada

        Jika tidak ada action item yang jelas, balas dengan array kosong: []

        Transkrip:
        {$transcript}
        PROMPT;

        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('action_items', $provider, $model, $prompt, $e->getMessage(), $meeting, $meeting->creator);
            Log::warning("Gagal membuat action items untuk Rapat ID {$meeting->id}: ".$e->getMessage());

            return;
        }

        $this->logger->logSuccess('action_items', $result->provider, $result->model, $prompt, $result->content, $result->promptTokens, $result->completionTokens, $result->durationMs, $meeting, $meeting->creator);

        $items = $this->actionItemsParser->parse($result->content);

        $meeting->actionItems()->delete();

        foreach ($items as $index => $item) {
            MeetingActionItem::create([
                'meeting_id' => $meeting->id,
                'title' => $item['title'],
                'assignee_name' => $item['assignee_name'],
                'deadline' => $item['deadline'],
                'order' => $index,
            ]);
        }

        Log::info(count($items)." action item dibuat untuk Rapat ID: {$meeting->id}");
    }

    private function notifyUnit(Meeting $meeting): void
    {
        if (! $meeting->unit_id) {
            Log::warning("Tidak ada unit_id untuk Rapat ID: {$meeting->id}, email tidak dikirim.");

            return;
        }

        $usersInUnit = User::where('unit_id', $meeting->unit_id)
            ->whereNotNull('email')
            ->get();

        if ($usersInUnit->isEmpty()) {
            Log::warning("Tidak ada user ditemukan di unit ID {$meeting->unit_id}, email tidak dikirim.");

            return;
        }

        Log::info("Mengirim email hasil rapat ke {$usersInUnit->count()} pengguna di unit ID {$meeting->unit_id}.");

        foreach ($usersInUnit as $user) {
            try {
                Mail::to($user->email)->send(new MeetingSummary($meeting));
                Log::info("Email hasil rapat terkirim ke {$user->email}");
            } catch (\Exception $e) {
                Log::error("Gagal mengirim email hasil rapat ke {$user->email}: ".$e->getMessage());
            }
        }
    }
}
