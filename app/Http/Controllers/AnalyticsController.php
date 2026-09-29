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

        $userQuery = User::query()->visibleTo($user)->with('unit:id,name');

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

        $taskQuery = Task::query()->visibleTo($user);

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

    public function overdue()
    {
        $user = Auth::user();
        $isSuperadmin = $user->seesAllUnits();

        $taskQuery = Task::query()
            ->visibleTo($user)
            ->whereNotIn('status', ['Done', 'Cancelled'])
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', today());

        $overdueTasks = $taskQuery
            ->with(['assignee:id,name', 'unit:id,name'])
            ->orderBy('deadline')
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'assignee' => $t->assignee?->name ?? $t->assignee_name,
                'unit' => $t->unit?->name,
                'priority' => $t->priority,
                'deadline' => $t->deadline->toDateString(),
                'days_overdue' => (int) $t->deadline->diffInDays(today()),
            ]);

        $byUnit = $overdueTasks
            ->groupBy(fn ($t) => $t['unit'] ?? 'Tidak diketahui')
            ->map->count()
            ->map(fn ($count, $unit) => ['unit' => $unit, 'count' => $count])
            ->values();

        $ageBuckets = collect([
            '1-3 hari' => fn ($d) => $d <= 3,
            '4-7 hari' => fn ($d) => $d > 3 && $d <= 7,
            '8-30 hari' => fn ($d) => $d > 7 && $d <= 30,
            '> 30 hari' => fn ($d) => $d > 30,
        ])->map(fn ($matcher, $label) => [
            'label' => $label,
            'count' => $overdueTasks->filter(fn ($t) => $matcher($t['days_overdue']))->count(),
        ])->values();

        return Inertia::render('Analytics/Overdue', [
            'overdueTasks' => $overdueTasks->values(),
            'byUnit' => $byUnit,
            'ageBuckets' => $ageBuckets,
            'isSuperadmin' => $isSuperadmin,
        ]);
    }
}
