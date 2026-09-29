<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingMarker;
use App\Services\Meeting\LiveRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Rekaman live rapat tatap muka (lihat LiveRecordingService). Hanya satu perangkat
 * perekam per rapat (live_user_id); anggota unit lain melihat transkrip berjalan,
 * boleh menambah penanda dan memberi nama pembicara.
 */
class LiveRecordingController extends Controller
{
    public function __construct(private readonly LiveRecordingService $live) {}

    public function start(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('process', $meeting);
        $validated = $request->validate(['mime_type' => 'required|string|max:100']);

        return $this->attempt(function () use ($meeting, $validated) {
            $this->live->start($meeting, Auth::user(), $validated['mime_type']);

            return $this->state($meeting->fresh());
        });
    }

    public function resume(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('process', $meeting);
        $validated = $request->validate(['mime_type' => 'required|string|max:100']);

        return $this->attempt(function () use ($meeting, $validated) {
            $this->live->resume($meeting, Auth::user(), $validated['mime_type']);

            return $this->state($meeting->fresh());
        });
    }

    public function append(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('process', $meeting);
        abort_unless($meeting->live_user_id === Auth::id(), 403, 'Perangkat lain sedang merekam rapat ini.');

        $validated = validator([
            'offset' => $request->header('X-Upload-Offset'),
            'seconds' => $request->header('X-Recording-Seconds'),
        ], [
            'offset' => 'required|integer|min:0',
            'seconds' => 'required|numeric|min:0|max:86400',
        ])->validate();

        return $this->attempt(function () use ($meeting, $validated, $request) {
            $state = $this->live->append($meeting, (int) $validated['offset'], (float) $validated['seconds'], $request->getContent(true));

            if ($state === null) {
                return response()->json(['message' => 'Offset tidak cocok.', ...$this->state($meeting->fresh())], 409);
            }

            return $this->state($meeting->fresh());
        });
    }

    public function stop(Meeting $meeting): JsonResponse
    {
        $this->authorize('process', $meeting);

        $this->live->stop($meeting, Auth::user());

        return response()->json($this->state($meeting->fresh()));
    }

    public function marker(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(MeetingMarker::TYPES))],
            'note' => 'nullable|string|max:500',
        ]);

        return $this->attempt(fn () => $this->live->addMarker($meeting, Auth::user(), $validated['type'], $validated['note'] ?? null)->only(['id', 'type', 'at_seconds', 'note']));
    }

    public function renameSpeaker(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);
        $validated = $request->validate([
            'from' => 'required|string|max:60',
            // Titik dua & baris baru akan merusak format "Nama: ucapan".
            'to' => ['required', 'string', 'max:60', 'not_regex:/[:\r\n]/'],
        ]);

        $this->live->renameSpeaker($meeting, trim($validated['from']), trim($validated['to']));

        return response()->json(['speaker_names' => $meeting->fresh()->speaker_names ?? []]);
    }

    /**
     * @param  callable(): (array<string, mixed>|JsonResponse)  $callback
     */
    private function attempt(callable $callback): JsonResponse
    {
        try {
            $result = $callback();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    /**
     * @return array{status: string, part: int, received_bytes: int, recorded_seconds: float}
     */
    private function state(Meeting $meeting): array
    {
        return [
            'status' => $meeting->status,
            'part' => $meeting->live_part,
            'received_bytes' => $meeting->live_bytes,
            'recorded_seconds' => $meeting->live_recorded_seconds,
        ];
    }
}
