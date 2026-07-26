<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Query dasar untuk statistik
        $meetingQuery = Meeting::query();
        $userQuery = \App\Models\User::query();

        // Terapkan filter unit jika bukan Super Admin
        if ($user->role !== 'superadmin') {
            $meetingQuery->where('unit_id', $user->unit_id);
            $userQuery->where('unit_id', $user->unit_id);
        }

        // Ambil statistik
        $stats = [
            'total_meetings' => $meetingQuery->count(),
            'processed_meetings' => (clone $meetingQuery)->where('status', 'Selesai Diproses')->count(),
            'total_users' => $userQuery->count(), // Super Admin melihat semua user, Admin/User melihat user di unitnya
        ];

        // Ambil rapat terbaru
        $recentMeetings = (clone $meetingQuery)
            ->orderBy('date', 'desc')
            ->take(5)
            ->get(['id', 'title', 'date', 'status']);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentMeetings' => $recentMeetings
        ]);
    }
}

