<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Services\Meeting\ActivityLogger;
use App\Services\Meeting\MeetingProcessingService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class MeetingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:Dijadwalkan,Berlangsung,Memproses,Selesai Diproses,Gagal',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $meetings = Meeting::query()
            ->visibleTo($request->user())
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('agenda', 'like', "%{$search}%")))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('date')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return MeetingResource::collection($meetings);
    }

    public function store(Request $request, ActivityLogger $activityLogger): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'agenda' => 'nullable|string',
            'attendees' => 'nullable|string|max:255',
        ]);

        $meeting = Meeting::create([
            'title' => $validated['title'],
            'date' => $validated['date'],
            'agenda' => HtmlSanitizer::clean($validated['agenda'] ?? null),
            'attendees' => $validated['attendees'] ?? null,
            'status' => 'Dijadwalkan',
            'unit_id' => $user->unit_id,
            'user_id' => $user->id,
        ]);

        $activityLogger->log($meeting, $user, 'meeting.created', "{$user->name} menjadwalkan rapat ini (via API).");

        return (new MeetingResource($meeting))->response()->setStatusCode(201);
    }

    public function show(Meeting $meeting): MeetingResource
    {
        $this->authorize('view', $meeting);

        return new MeetingResource($meeting->load('actionItems'));
    }

    /**
     * Kirim transkrip jadi (mis. hasil ekspor Zoom/Meet/Teams) untuk dirangkum AI.
     */
    public function submitTranscript(Request $request, Meeting $meeting, MeetingProcessingService $processingService): JsonResponse
    {
        $this->authorize('process', $meeting);

        if (! MeetingProcessingService::canStart($meeting)) {
            return response()->json(['message' => 'Rapat ini sedang atau sudah diproses.'], 409);
        }

        $validated = $request->validate([
            'transcript' => 'required|string|max:2000000',
        ]);

        $path = 'text_uploads/api_input_'.$meeting->id.'_'.time().'.txt';
        Storage::disk('public')->put($path, $validated['transcript']);

        $processingService->start($meeting, $path, $request->user());

        return (new MeetingResource($meeting->fresh()))->response()->setStatusCode(202);
    }
}
