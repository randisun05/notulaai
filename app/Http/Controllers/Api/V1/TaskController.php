<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\Task\TaskStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(Task::STATUSES)],
            'assigned_to_me' => 'nullable|boolean',
            'meeting_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $tasks = Task::query()
            ->visibleTo($request->user())
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($request->boolean('assigned_to_me'), fn ($q) => $q->where('assignee_id', $request->user()->id))
            ->when($validated['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->with('assignee:id,name')
            ->orderBy('deadline')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load('assignee:id,name'));
    }

    public function updateStatus(Request $request, Task $task, TaskStatusService $statusService): TaskResource|JsonResponse
    {
        $this->authorize('update', $task);

        if ($reason = $statusService->denialReason($task, $request->user())) {
            return response()->json(['message' => $reason], 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(TaskStatusService::SELECTABLE_STATUSES)],
        ], [
            'status.in' => TaskStatusService::INVALID_STATUS_MESSAGE,
        ]);

        $statusService->change($task, $validated['status'], $request->user());

        return new TaskResource($task->fresh('assignee'));
    }
}
