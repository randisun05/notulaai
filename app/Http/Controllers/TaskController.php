<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $tasks = Task::query()
            ->when(!$user->hasRole('superadmin'), fn ($q) => $q->where('unit_id', $user->unit_id))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->with(['meeting:id,title', 'assignee:id,name'])
            ->orderByRaw("CASE status WHEN 'Done' THEN 1 WHEN 'Cancelled' THEN 1 ELSE 0 END")
            ->orderBy('deadline')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only(['status']),
            'statuses' => Task::STATUSES,
        ]);
    }

    public function storeFromActionItem(Meeting $meeting, MeetingActionItem $actionItem)
    {
        $this->authorize('update', $meeting);

        if ($actionItem->meeting_id !== $meeting->id) {
            abort(404);
        }

        if ($actionItem->converted_to_task) {
            return back()->with('error', 'Action item ini sudah pernah dijadikan Task.');
        }

        $assignee = null;
        if ($actionItem->assignee_name) {
            $assignee = User::where('unit_id', $meeting->unit_id)
                ->where('name', 'like', $actionItem->assignee_name)
                ->first();
        }

        Task::create([
            'meeting_id' => $meeting->id,
            'meeting_action_item_id' => $actionItem->id,
            'unit_id' => $meeting->unit_id,
            'assignee_id' => $assignee?->id,
            'created_by' => Auth::id(),
            'title' => $actionItem->title,
            'assignee_name' => $actionItem->assignee_name,
            'deadline' => $actionItem->deadline,
        ]);

        $actionItem->update(['converted_to_task' => true]);

        return back()->with('success', 'Action item berhasil dijadikan Task.');
    }

    public function updateStatus(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Task::STATUSES)],
        ]);

        $task->update($validated);

        return back()->with('success', 'Status task berhasil diperbarui.');
    }
}
