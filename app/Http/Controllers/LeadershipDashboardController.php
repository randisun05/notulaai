<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Models\MeetingMinutes;
use App\Models\Task;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Dasbor pimpinan: kinerja rapat & tindak lanjut semua unit dalam satu layar
 * (hanya baca; peran pimpinan & superadmin — Gate view-leadership-dashboard).
 */
class LeadershipDashboardController extends Controller
{
    public const PERIODS = [30 => '30 hari', 90 => '3 bulan', 365 => '12 bulan'];

    private const OPEN = ['Done', 'Cancelled'];

    public function index(Request $request)
    {
        $days = array_key_exists((int) $request->query('days'), self::PERIODS) ? (int) $request->query('days') : 90;
        $since = now()->subDays($days)->startOfDay();
        $sinceDate = $since->toDateString();
        $today = today()->toDateString();

        $count = fn ($query) => $query->selectRaw('unit_id, count(*) as total')->groupBy('unit_id')->pluck('total', 'unit_id');

        $meetings = $count(Meeting::where('date', '>=', $sinceDate));
        $processed = $count(Meeting::where('date', '>=', $sinceDate)->where('status', 'Selesai Diproses'));
        $decisions = $count(MeetingDecision::whereHas('meeting', fn ($q) => $q->where('date', '>=', $sinceDate)));
        $openTasks = $count(Task::whereNotIn('status', self::OPEN));
        $overdueTasks = $count(Task::whereNotIn('status', self::OPEN)->whereNotNull('deadline')->where('deadline', '<', $today));
        $tasksInPeriod = $count(Task::where('created_at', '>=', $since)->where('status', '!=', 'Cancelled'));
        $doneInPeriod = $count(Task::where('created_at', '>=', $since)->where('status', 'Done'));
        $minutesApproved = MeetingMinutes::query()
            ->join('meetings', 'meetings.id', '=', 'meeting_minutes.meeting_id')
            ->where('meeting_minutes.status', MeetingMinutes::STATUS_APPROVED)
            ->where('meetings.date', '>=', $sinceDate)
            ->selectRaw('meetings.unit_id, count(*) as total')->groupBy('meetings.unit_id')->pluck('total', 'unit_id');

        $units = Unit::orderBy('name')->get(['id', 'name'])->map(function (Unit $unit) use ($meetings, $processed, $decisions, $openTasks, $overdueTasks, $tasksInPeriod, $doneInPeriod, $minutesApproved) {
            $tasks = (int) ($tasksInPeriod[$unit->id] ?? 0);

            return [
                'id' => $unit->id,
                'name' => $unit->name,
                'meetings' => (int) ($meetings[$unit->id] ?? 0),
                'processed' => (int) ($processed[$unit->id] ?? 0),
                'minutes_approved' => (int) ($minutesApproved[$unit->id] ?? 0),
                'decisions' => (int) ($decisions[$unit->id] ?? 0),
                'open_tasks' => (int) ($openTasks[$unit->id] ?? 0),
                'overdue_tasks' => (int) ($overdueTasks[$unit->id] ?? 0),
                'tasks' => $tasks,
                'completion_rate' => $tasks > 0 ? (int) round(($doneInPeriod[$unit->id] ?? 0) / $tasks * 100) : null,
            ];
        });

        $sum = fn (string $key) => $units->sum($key);
        $totalTasks = $sum('tasks');

        return Inertia::render('Leadership/Index', [
            'days' => $days,
            'periods' => self::PERIODS,
            'units' => $units->values(),
            'totals' => [
                'meetings' => $sum('meetings'),
                'processed' => $sum('processed'),
                'minutes_approved' => $sum('minutes_approved'),
                'decisions' => $sum('decisions'),
                'open_tasks' => $sum('open_tasks'),
                'overdue_tasks' => $sum('overdue_tasks'),
                'completion_rate' => $totalTasks > 0 ? (int) round($doneInPeriod->sum() / $totalTasks * 100) : null,
            ],
            'overdue' => Task::query()
                ->whereNotIn('status', self::OPEN)->whereNotNull('deadline')->where('deadline', '<', $today)
                ->with(['unit:id,name', 'assignee:id,name', 'meeting:id,title'])
                ->orderBy('deadline')->limit(10)->get()
                ->map(fn (Task $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'unit' => $task->unit?->name,
                    'assignee' => $task->assignee->name ?? $task->assignee_name,
                    'deadline' => $task->deadline->toDateString(),
                    'days_overdue' => (int) $task->deadline->diffInDays(today()),
                    'status' => $task->status,
                    'meeting' => $task->meeting?->only(['id', 'title']),
                ]),
            'recentDecisions' => MeetingDecision::query()
                ->with(['meeting:id,title,date,unit_id', 'meeting.unit:id,name', 'actionItem.task'])
                ->orderByDesc(Meeting::select('date')->whereColumn('meetings.id', 'meeting_decisions.meeting_id'))
                ->orderBy('order')
                ->limit(10)->get(),
            'pendingMinutes' => MeetingMinutes::query()
                ->where('status', MeetingMinutes::STATUS_SUBMITTED)
                ->with(['meeting:id,title,date,unit_id', 'meeting.unit:id,name'])
                ->orderBy('submitted_at')->limit(10)->get()
                ->map(fn (MeetingMinutes $minutes) => [
                    'meeting' => $minutes->meeting->only(['id', 'title', 'date']),
                    'unit' => $minutes->meeting->unit?->name,
                    'submitted_at' => $minutes->submitted_at?->toDateTimeString(),
                    'chairperson' => $minutes->chairpersonDisplayName(),
                ]),
        ]);
    }
}
