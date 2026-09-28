<?php

namespace App\Services\Meeting;

use App\Jobs\CutLiveWindow;
use App\Jobs\FinishLiveRecording;
use App\Jobs\TranscribeMeetingSegment;
use App\Models\Meeting;
use App\Models\MeetingMarker;
use App\Models\MeetingSegment;
use App\Models\User;
use App\Services\Audio\AudioSplitter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Rekaman live rapat tatap muka. Browser perekam mengirim audio tiap ±5 detik
 * (append) ke file "bagian" yang terus bertambah; tiap ±60 detik rekaman baru
 * dipotong di jeda hening (cut) dan potongannya ditranskrip oleh
 * TranscribeMeetingSegment selagi rapat berjalan. Stop → sisa rekaman dipotong,
 * lalu alur finalisasi yang sama dengan upload rekaman.
 *
 * Status: Dijadwalkan/Gagal → Berlangsung → Memproses → Selesai Diproses.
 * processing_stage selama ini: live → closing (stop, menunggu potongan terakhir)
 * → transcribing → summarizing.
 */
class LiveRecordingService
{
    private const EXTENSIONS = ['webm' => 'webm', 'ogg' => 'ogg', 'mp4' => 'mp4', 'aac' => 'aac', 'mpeg' => 'mp3'];

    public function __construct(
        private readonly AudioSplitter $audioSplitter,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function start(Meeting $meeting, User $user, string $mimeType): void
    {
        if (! MeetingProcessingService::canStart($meeting)) {
            throw new RuntimeException('Rapat ini sedang atau sudah diproses.');
        }

        $extension = $this->extensionFor($mimeType);

        // Mulai bersih (mis. setelah percobaan sebelumnya Gagal).
        $meeting->segments()->delete();
        $meeting->markers()->delete();
        Storage::disk('local')->deleteDirectory("meeting_segments/{$meeting->id}");

        $meeting->update([
            'status' => 'Berlangsung',
            'processing_stage' => 'live',
            'processing_total_segments' => 0,
            'processing_heartbeat_at' => now(),
            'live_user_id' => $user->id,
            'live_started_at' => now(),
            'live_part' => 1,
            'live_extension' => $extension,
            'live_bytes' => 0,
            'live_part_offset_seconds' => 0,
            'live_recorded_seconds' => 0,
            'live_cursor_seconds' => 0,
            'speaker_names' => null,
            'source_disk' => 'local',
            'source_file_path' => null,
        ]);
        $this->openPart($meeting, 1);

        $this->activityLogger->log($meeting, $user, 'meeting.live_started', "{$user->name} memulai rekaman live rapat ini.");
    }

    /**
     * Perekam tertutup (reload/crash) lalu melanjutkan: sisa bagian lama ditutup
     * dan ditranskrip, rekaman berlanjut di file bagian baru (stream MediaRecorder
     * baru tidak bisa disambung ke file lama).
     */
    public function resume(Meeting $meeting, User $user, string $mimeType): void
    {
        $this->assertLive($meeting);

        $this->locked($meeting, function () use ($meeting, $user, $mimeType) {
            $this->cutLocked($meeting, final: true);

            $meeting->refresh();
            $part = $meeting->live_part + 1;
            $meeting->update([
                'live_user_id' => $user->id,
                'live_part' => $part,
                'live_extension' => $this->extensionFor($mimeType),
                'live_bytes' => 0,
                'live_part_offset_seconds' => $meeting->live_recorded_seconds,
                'processing_heartbeat_at' => now(),
            ]);
            $this->openPart($meeting, $part);
        });

        $this->activityLogger->log($meeting, $user, 'meeting.live_resumed', "{$user->name} melanjutkan rekaman live.");
    }

    /**
     * Tambahkan potongan audio dari browser perekam.
     *
     * @param  resource  $body
     * @param  float  $partSeconds  durasi rekaman bagian aktif yang sudah terkirim (jam perekam)
     * @return array{received_bytes: int, recorded_seconds: float}|null null kalau offset tidak cocok
     */
    public function append(Meeting $meeting, int $offset, float $partSeconds, $body): ?array
    {
        $this->assertLive($meeting);

        $state = $this->locked($meeting, function () use ($meeting, $offset, $partSeconds, $body) {
            $meeting->refresh();
            if ($offset !== $meeting->live_bytes) {
                return null;
            }

            $path = Storage::disk('local')->path($meeting->livePartPath($meeting->live_part));
            $handle = fopen($path, 'c+b');
            // Buang sisa tulisan setengah jadi dari request yang terputus.
            ftruncate($handle, $meeting->live_bytes);
            fseek($handle, $meeting->live_bytes);
            $written = stream_copy_to_stream($body, $handle, (int) Config::get('ai.audio.max_chunk_bytes') + 1);
            fclose($handle);

            if ($written === false || $written > (int) Config::get('ai.audio.max_chunk_bytes')) {
                throw new RuntimeException('Potongan audio tidak valid.');
            }

            $meeting->update([
                'live_bytes' => $meeting->live_bytes + $written,
                'live_recorded_seconds' => max($meeting->live_recorded_seconds, $meeting->live_part_offset_seconds + max(0, $partSeconds)),
                'processing_heartbeat_at' => now(),
            ]);

            return ['received_bytes' => $meeting->live_bytes, 'recorded_seconds' => $meeting->live_recorded_seconds];
        });

        if ($state && (int) Config::get('ai.live.window_seconds', 60) <= $state['recorded_seconds'] - $meeting->fresh()->live_cursor_seconds) {
            CutLiveWindow::dispatch($meeting);
        }

        return $state;
    }

    /**
     * Potong jendela berikutnya kalau rekaman baru sudah cukup (dipanggil job CutLiveWindow).
     */
    public function cut(Meeting $meeting): void
    {
        if ($meeting->fresh()->status !== 'Berlangsung') {
            return;
        }

        $this->locked($meeting, function () use ($meeting) {
            // Beberapa jendela bisa tertunda (mis. antrean sempat penuh).
            while ($this->cutLocked($meeting, final: false)) {
                // lanjut
            }
        });
    }

    public function stop(Meeting $meeting, ?User $user, string $reason = ''): void
    {
        $updated = Meeting::whereKey($meeting->id)
            ->where('status', 'Berlangsung')
            ->update(['status' => 'Memproses', 'processing_stage' => 'closing', 'processing_heartbeat_at' => now()]);

        if (! $updated) {
            return;
        }

        $meeting->refresh();
        $this->activityLogger->log($meeting, $user, 'meeting.live_stopped', $user
            ? "{$user->name} mengakhiri rekaman live. Notula sedang dibuat."
            : 'Rekaman live terputus'.($reason ? " ({$reason})" : '').', rekaman yang sudah masuk diproses apa adanya.');

        FinishLiveRecording::dispatch($meeting);
    }

    /**
     * Potong sisa rekaman, lalu serahkan ke alur finalisasi biasa (dipanggil job FinishLiveRecording).
     */
    public function finish(Meeting $meeting, MeetingProcessingService $processingService): void
    {
        $meeting->refresh();
        if ($meeting->status !== 'Memproses' || $meeting->processing_stage !== 'closing') {
            return;
        }

        $this->locked($meeting, fn () => $this->cutLocked($meeting, final: true));

        $meeting->refresh();
        $meeting->update([
            'processing_stage' => 'transcribing',
            'processing_total_segments' => $meeting->segments()->count(),
            // Rekaman yang diputar setelah rapat: bagian pertama (digabung saat finalisasi kalau lebih dari satu).
            'source_file_path' => $meeting->livePartPath(1),
        ]);

        if ($meeting->processing_total_segments === 0) {
            throw new RuntimeException('Tidak ada rekaman yang masuk.');
        }

        $processingService->claimFinalization($meeting);
    }

    public function addMarker(Meeting $meeting, User $user, string $type, ?string $note): MeetingMarker
    {
        $this->assertLive($meeting);

        return $meeting->markers()->create([
            'user_id' => $user->id,
            'type' => $type,
            // Posisi di rekaman menurut server (tertinggal ≤ 5 detik dari perekam).
            'at_seconds' => $meeting->live_recorded_seconds,
            'note' => $note,
        ]);
    }

    /**
     * "Pembicara 1" → "Pak Budi". Berlaku untuk transkrip live, transkrip final,
     * dan penggantian berikutnya ("Pak Budi" → "Budi Santoso").
     */
    public function renameSpeaker(Meeting $meeting, string $from, string $to): void
    {
        $names = $meeting->speaker_names ?? [];
        $renamedExisting = false;

        foreach ($names as $label => $name) {
            if ($name === $from) {
                $names[$label] = $to;
                $renamedExisting = true;
            }
        }
        if (! $renamedExisting) {
            $names[$from] = $to;
        }

        $meeting->speaker_names = $names;

        // Transkrip final sudah menyimpan nama hasil penggantian sebelumnya.
        if ($meeting->transcript) {
            $meeting->transcript = preg_replace('/^'.preg_quote($from, '/').':/mu', addcslashes($to, '\\$').':', $meeting->transcript);
        }

        $meeting->save();
    }

    /**
     * @return bool true kalau satu potongan dibuat (ada kemungkinan potongan berikutnya)
     */
    private function cutLocked(Meeting $meeting, bool $final): bool
    {
        $meeting->refresh();
        $window = (float) Config::get('ai.live.window_seconds', 60);
        $maxWindow = (float) Config::get('ai.live.max_window_seconds', 90);

        $available = $meeting->live_recorded_seconds - $meeting->live_cursor_seconds;
        $localStart = $meeting->live_cursor_seconds - $meeting->live_part_offset_seconds;
        $source = Storage::disk('local')->path($meeting->livePartPath($meeting->live_part));

        if ($available < ($final ? 0.5 : $window) || $meeting->live_bytes === 0) {
            return false;
        }

        if ($final) {
            $duration = null;
            $end = $meeting->live_recorded_seconds;
        } else {
            // Cari jeda di antara detik ke-60 dan ke-90 jendela ini, supaya potongan
            // jatuh di sela orang bicara, bukan di tengah kalimat.
            $searchLength = min($maxWindow, $available) - $window;
            $silence = $searchLength > 0.5
                ? $this->audioSplitter->findSilence($source, $localStart + $window, $searchLength)
                : null;

            if ($silence === null) {
                if ($available < $maxWindow) {
                    return false; // tunggu audio berikutnya, mungkin jedanya sebentar lagi
                }
                $silence = $localStart + $maxWindow; // tidak ada jeda sama sekali: potong paksa
            }

            $duration = $silence - $localStart;
            $end = $meeting->live_cursor_seconds + $duration;
        }

        $index = (int) $meeting->segments()->max('index') + ($meeting->segments()->exists() ? 1 : 0);
        $audioPath = "meeting_segments/{$meeting->id}/live_".sprintf('%04d', $index).'.mp3';
        $this->audioSplitter->extract($source, $localStart, $duration, Storage::disk('local')->path($audioPath));

        $segment = MeetingSegment::create([
            'meeting_id' => $meeting->id,
            'index' => $index,
            'start_seconds' => $meeting->live_cursor_seconds,
            'end_seconds' => $end,
            'audio_path' => $audioPath,
        ]);

        $meeting->update([
            'live_cursor_seconds' => $end,
            'processing_total_segments' => $index + 1,
        ]);

        TranscribeMeetingSegment::dispatch($segment);

        return ! $final;
    }

    private function openPart(Meeting $meeting, int $part): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory("recordings/{$meeting->id}");
        $disk->put($meeting->livePartPath($part), '');
    }

    private function assertLive(Meeting $meeting): void
    {
        if ($meeting->status !== 'Berlangsung') {
            throw new RuntimeException('Rekaman live rapat ini sudah tidak berjalan.');
        }
    }

    private function extensionFor(string $mimeType): string
    {
        foreach (self::EXTENSIONS as $needle => $extension) {
            if (str_contains(strtolower($mimeType), $needle)) {
                return $extension;
            }
        }

        throw new RuntimeException("Format rekaman browser tidak didukung: {$mimeType}");
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(Meeting $meeting, callable $callback): mixed
    {
        return Cache::lock("live-meeting:{$meeting->id}", 120)->block(30, $callback);
    }
}
