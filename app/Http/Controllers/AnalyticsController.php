<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function productivity()
    {
        $user = Auth::user();
        $isSuperadmin = $user->hasRole('superadmin');

        $userQuery = User::query()->with('unit:id,name');
        if (!$isSuperadmin) {
            $userQuery->where('unit_id', $user->unit_id);
        }

        $productivity = $userQuery
            ->withCount([
                'assignedTasks as completed_count' => fn ($q) => $q->where('status', 'Done'),
                'assignedTasks as total_count' => fn ($q) => $q->where('status', '!=', 'Cancelled'),
                'assignedTasks as overdue_count' => fn ($q) => $q->whereNotIn('status', ['Done', 'Cancelled'])
                    ->whereNotNull('deadline')
                    ->whereDate('deadline', '<', today()),
            ])
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'unit' => $u->unit?->name,
                'completed_count' => $u->completed_count,
                'total_count' => $u->total_count,
                'overdue_count' => $u->overdue_count,
                'completion_rate' => $u->total_count > 0 ? (int) round($u->completed_count / $u->total_count * 100) : 0,
            ])
            ->filter(fn ($row) => $row['total_count'] > 0)
            ->sortByDesc('completed_count')
            ->values();

        return Inertia::render('Analytics/Productivity', [
            'productivity' => $productivity,
        ]);
    }

    /**
     * Heatmap kalender jumlah Task yang dibuat per hari, 12 minggu terakhir.
     */
    public function heatmap()
    {
        $user = Auth::user();
        $isSuperadmin = $user->hasRole('superadmin');

        $taskQuery = Task::query();
        if (!$isSuperadmin) {
            $taskQuery->where('unit_id', $user->unit_id);
        }

        $today = Carbon::today();
        $rangeStart = $today->copy()->subWeeks(11)->startOfWeek(Carbon::SUNDAY);

        $counts = (clone $taskQuery)
            ->where('created_at', '>=', $rangeStart)
            ->get(['created_at'])
            ->groupBy(fn (Task $t) => $t->created_at->toDateString())
            ->map->count();

        $weeks = [];
        $cursor = $rangeStart->copy();
        while ($cursor->lte($today)) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $week[] = $cursor->gt($today) ? null : [
                    'date' => $cursor->toDateString(),
                    'count' => $counts->get($cursor->toDateString(), 0),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return Inertia::render('Analytics/Heatmap', [
            'weeks' => $weeks,
            'maxCount' => $counts->max() ?? 0,
        ]);
    }
}
