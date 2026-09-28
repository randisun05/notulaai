<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\RecordingUpload;
use App\Services\Meeting\MeetingProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

/**
 * Upload rekaman bertahap: browser mengirim potongan beberapa MB sebagai raw body
 * (tidak kena upload_max_filesize PHP), server menambahkannya ke file sementara
 * di disk privat. Koneksi putus → pilih file yang sama lagi, upload berlanjut dari
 * byte terakhir yang diterima.
 */
class RecordingUploadController extends Controller
{
    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('process', $meeting);

        if (! MeetingProcessingService::canStart($meeting)) {
            return response()->json(['message' => 'Rapat ini sedang atau sudah diproses.'], 409);
        }

        $maxBytes = (int) Config::get('ai.audio.max_upload_mb') * 1024 * 1024;

        $validated = $request->validate([
            'file_name' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! in_array(self::extension($value), MeetingProcessingService::AUDIO_EXTENSIONS, true)) {
                    $fail('Format rekaman tidak didukung. Gunakan mp3, wav, m4a, mp4, webm, ogg, aac, flac, mov, atau mkv.');
                }
            }],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
        ], [
            'file_size.max' => 'Ukuran rekaman maksimal '.Config::get('ai.audio.max_upload_mb').' MB.',
        ]);

        // File yang sama dipilih lagi setelah putus → lanjutkan upload yang ada.
        $upload = RecordingUpload::firstOrCreate([
            'meeting_id' => $meeting->id,
            'user_id' => Auth::id(),
            'file_name' => $validated['file_name'],
            'file_size' => $validated['file_size'],
        ], ['received_bytes' => 0]);

        return response()->json($this->state($upload), $upload->wasRecentlyCreated ? 201 : 200);
    }

    public function append(Request $request, RecordingUpload $upload): JsonResponse
    {
        $this->authorizeUpload($upload);

        $validated = validator(['offset' => $request->header('X-Upload-Offset')], [
            'offset' => ['required', 'integer', 'min:0'],
        ])->validate();
        $offset = (int) $validated['offset'];

        return Cache::lock("recording-upload:{$upload->id}", 60)->block(15, function () use ($request, $upload, $offset) {
            $upload->refresh();

            // Klien & server tidak sepakat posisi (mis. respons sebelumnya hilang):
            // beri tahu posisi yang benar, klien melanjutkan dari sana.
            if ($offset !== $upload->received_bytes) {
                return response()->json(['message' => 'Offset tidak cocok.', ...$this->state($upload)], 409);
            }

            $maxChunk = (int) Config::get('ai.audio.max_chunk_bytes');
            $body = $request->getContent(true);

            $disk = Storage::disk('local');
            $disk->makeDirectory('recording_uploads');
            $handle = fopen($disk->path($upload->partialPath()), 'c+b');
            // Buang sisa tulisan setengah jadi dari request yang terputus.
            ftruncate($handle, $upload->received_bytes);
            fseek($handle, $upload->received_bytes);
            $written = stream_copy_to_stream($body, $handle, $maxChunk + 1);
            fclose($handle);

            if ($written === false || $written === 0 || $written > $maxChunk || $upload->received_bytes + $written > $upload->file_size) {
                return response()->json(['message' => 'Potongan upload tidak valid.', ...$this->state($upload)], 422);
            }

            $upload->update(['received_bytes' => $upload->received_bytes + $written]);

            return response()->json($this->state($upload));
        });
    }

    public function complete(RecordingUpload $upload, MeetingProcessingService $processingService): JsonResponse
    {
        $this->authorizeUpload($upload);

        if (! $upload->isComplete()) {
            return response()->json(['message' => 'Upload belum lengkap.', ...$this->state($upload)], 422);
        }

        $disk = Storage::disk('local');
        $mime = (string) $disk->mimeType($upload->partialPath());

        if (! str_starts_with($mime, 'audio/') && ! str_starts_with($mime, 'video/')) {
            $this->discard($upload);

            return response()->json(['message' => 'File yang diunggah bukan rekaman audio/video.'], 422);
        }

        $meeting = $upload->meeting;
        $path = "recordings/{$meeting->id}/{$upload->id}.".self::extension($upload->file_name);
        $disk->move($upload->partialPath(), $path);
        $upload->delete();

        $processingService->start($meeting, $path, Auth::user(), disk: 'local');

        return response()->json(['status' => $meeting->fresh()->status]);
    }

    public function destroy(RecordingUpload $upload): JsonResponse
    {
        $this->authorizeUpload($upload);

        $this->discard($upload);

        return response()->json(null, 204);
    }

    private function authorizeUpload(RecordingUpload $upload): void
    {
        abort_unless($upload->user_id === Auth::id(), 403);
        $this->authorize('process', $upload->meeting);

        abort_unless(MeetingProcessingService::canStart($upload->meeting), 409, 'Rapat ini sedang atau sudah diproses.');
    }

    private function discard(RecordingUpload $upload): void
    {
        Storage::disk('local')->delete($upload->partialPath());
        $upload->delete();
    }

    /**
     * @return array{id: string, received_bytes: int, file_size: int, chunk_bytes: int}
     */
    private function state(RecordingUpload $upload): array
    {
        return [
            'id' => $upload->id,
            'received_bytes' => $upload->received_bytes,
            'file_size' => $upload->file_size,
            'chunk_bytes' => (int) Config::get('ai.audio.chunk_bytes'),
        ];
    }

    private static function extension(string $fileName): string
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }
}
