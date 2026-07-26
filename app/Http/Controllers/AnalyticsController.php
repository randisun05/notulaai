<?php

namespace App\Http\Controllers;

use App\Models\User;
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
}
