<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => 'nullable|in:'.implode(',', Task::STATUSES),
            'assignee_id' => 'nullable|integer',
            'meeting_id' => 'nullable|integer',
            'overdue' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $tasks = Task::query()
            ->visibleTo($request->user())
            ->with(['assignee:id,name,email', 'meeting:id,title'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['assignee_id'] ?? null, fn ($q, $id) => $q->where('assignee_id', $id))
            ->when($filters['meeting_id'] ?? null, fn ($q, $id) => $q->where('meeting_id', $id))
            // Same rule as Task::getIsOverdueAttribute(): past deadline and not Done/Cancelled.
            ->when($request->boolean('overdue'), fn ($q) => $q
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', now()->toDateString())
                ->whereNotIn('status', ['Done', 'Cancelled']))
            ->orderByRaw('deadline IS NULL')
            ->orderBy('deadline')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function show(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load(['assignee:id,name,email', 'meeting:id,title']));
    }
}
