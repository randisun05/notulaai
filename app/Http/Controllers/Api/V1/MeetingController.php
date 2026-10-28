<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public const STATUSES = ['Dijadwalkan', 'Memproses', 'Selesai Diproses', 'Gagal'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:200',
            'status' => 'nullable|in:'.implode(',', self::STATUSES),
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $meetings = Meeting::query()
            ->visibleTo($request->user())
            ->with('unit:id,name')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->search($search))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return MeetingResource::collection($meetings);
    }

    public function show(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        return new MeetingResource($meeting->load(['unit:id,name', 'actionItems']));
    }
}
