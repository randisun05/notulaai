<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Daftar Keputusan: register keputusan semua rapat yang boleh dilihat user,
 * bisa dicari & difilter, masing-masing terhubung ke rapat dan status tindak lanjutnya.
 */
class DecisionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $filters = $request->validate([
            'q' => 'nullable|string|max:200',
            'unit_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'status' => ['nullable', Rule::in(array_keys(MeetingDecision::FOLLOW_UP_STATUSES))],
        ]);

        $decisions = MeetingDecision::query()
            ->visibleTo($user)
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('meeting_decisions.text', 'like', "%{$term}%")
                ->orWhereHas('meeting', fn ($m) => $m->where('title', 'like', "%{$term}%"))))
            ->when(($filters['unit_id'] ?? null) && $user->seesAllUnits(), fn ($q) => $q->where('meeting_decisions.unit_id', $filters['unit_id']))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereHas('meeting', fn ($m) => $m->where('date', '>=', $from)))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereHas('meeting', fn ($m) => $m->where('date', '<', date('Y-m-d', strtotime($to.' +1 day')))))
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'selesai' => $q->whereHas('actionItem.task', fn ($t) => $t->where('status', 'Done')),
                'berjalan' => $q->whereHas('actionItem.task', fn ($t) => $t->whereNotIn('status', ['Done', 'Cancelled'])),
                'belum' => $q->whereHas('actionItem', fn ($a) => $a->whereDoesntHave('task')),
                'tanpa' => $q->where(fn ($q) => $q->whereNull('meeting_action_item_id')
                    ->orWhereHas('actionItem.task', fn ($t) => $t->where('status', 'Cancelled'))),
                default => $q,
            })
            ->with(['meeting:id,title,date,unit_id', 'meeting.unit:id,name', 'actionItem.task'])
            ->orderByDesc(Meeting::select('date')->whereColumn('meetings.id', 'meeting_decisions.meeting_id'))
            ->orderBy('order')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Decisions/Index', [
            'decisions' => $decisions,
            'filters' => (object) array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            'statuses' => MeetingDecision::FOLLOW_UP_STATUSES,
            'units' => $user->seesAllUnits() ? Unit::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }
}
