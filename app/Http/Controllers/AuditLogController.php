<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('access-admin-panel');

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->input('search'), fn ($q, $search) => $q->where('description', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => $request->only(['action', 'search']),
        ]);
    }
}
