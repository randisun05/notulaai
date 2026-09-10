<?php

namespace App\Http\Controllers;

use App\Exports\TasksExport;
use App\Models\Task;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class TaskExportController extends Controller
{
    public function pdf(Request $request)
    {
        $pdf = Pdf::loadView('exports.tasks-pdf', ['tasks' => $this->filteredTasks($request)]);

        return $pdf->download('tasks-'.now()->format('Y-m-d').'.pdf');
    }

    public function excel(Request $request)
    {
        return Excel::download(new TasksExport($this->filteredTasks($request)), 'tasks-'.now()->format('Y-m-d').'.xlsx');
    }

    private function filteredTasks(Request $request)
    {
        $user = Auth::user();

        return Task::query()
            ->visibleTo($user)
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereNotIn('status', ['Done', 'Cancelled'])
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', today()))
            ->with(['unit:id,name', 'assignee:id,name'])
            ->orderBy('deadline')
            ->get();
    }
}
