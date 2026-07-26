<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isSuperadmin = $user->hasRole('superadmin');

        $meetingQuery = Meeting::query();
        $taskQuery = Task::query();
        $userQuery = User::query();

        if (!$isSuperadmin) {
            $meetingQuery->where('unit_id', $user->unit_id);
            $taskQuery->where('unit_id', $user->unit_id);
            $userQuery->where('unit_id', $user->unit_id);
        }

        $totalTasks = (clone $taskQuery)->where('status', '!=', 'Cancelled')->count();
        $completedTasks = (clone $taskQuery)->where('status', 'Done')->count();

        $stats = [
            'total_meetings' => (clone $meetingQuery)->count(),
            'processed_meetings' => (clone $meetingQuery)->where('status', 'Selesai Diproses')->count(),
            'total_users' => $userQuery->count(),
            'meetings_today' => (clone $meetingQuery)->whereDate('date', today())->count(),
            'meetings_this_week' => (clone $meetingQuery)->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'tasks_completed' => $completedTasks,
            'tasks_overdue' => (clone $taskQuery)
                ->whereNotIn('status', ['Done', 'Cancelled'])
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', today())
                ->count(),
            'progress_percent' => $totalTasks > 0 ? (int) round($completedTasks / $totalTasks * 100) : 0,
        ];

        $recentMeetings = (clone $meetingQuery)
            ->orderBy('date', 'desc')
            ->take(5)
            ->get(['id', 'title', 'date', 'status']);

        // Volume rapat per minggu, 8 minggu terakhir — untuk chart tren.
        $weeklyMeetings = collect(range(7, 0))->map(function ($weeksAgo) use ($meetingQuery) {
            $weekStart = now()->subWeeks($weeksAgo)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            return [
                'label' => $weekStart->format('d M'),
                'count' => (clone $meetingQuery)->whereBetween('date', [$weekStart, $weekEnd])->count(),
            ];
        })->values();

        $taskStatusBreakdown = collect(Task::STATUSES)->map(fn ($status) => [
            'status' => $status,
            'count' => (clone $taskQuery)->where('status', $status)->count(),
        ])->values();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentMeetings' => $recentMeetings,
            'weeklyMeetings' => $weeklyMeetings,
            'taskStatusBreakdown' => $taskStatusBreakdown,
        ]);
    }
}
