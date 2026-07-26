<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Task;
use App\Models\User;
use App\Services\Meeting\ActivityLogger;
use App\Services\Webhook\WebhookDispatcher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly WebhookDispatcher $webhookDispatcher,
    ) {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $tasks = Task::query()
            ->visibleTo($user)
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

    public function kanban()
    {
        $user = Auth::user();

        $tasks = Task::query()
            ->visibleTo($user)
            ->with(['meeting:id,title', 'assignee:id,name'])
            ->orderBy('deadline')
            ->get()
            ->groupBy('status');

        $tasksByStatus = collect(Task::STATUSES)->mapWithKeys(fn ($status) => [
            $status => ($tasks->get($status) ?? collect())->values(),
        ]);

        return Inertia::render('Tasks/Kanban', [
            'tasksByStatus' => $tasksByStatus,
            'statuses' => Task::STATUSES,
        ]);
    }

    public function calendar(Request $request)
    {
        $user = Auth::user();
        $month = max(1, min(12, (int) $request->input('month', now()->month)));
        $year = (int) $request->input('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $tasks = Task::query()
            ->visibleTo($user)
            ->whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
            ->with(['assignee:id,name'])
            ->orderBy('deadline')
            ->get()
            ->groupBy(fn (Task $task) => $task->deadline->toDateString());

        return Inertia::render('Tasks/Calendar', [
            'tasksByDate' => $tasks,
            'month' => $month,
            'year' => $year,
        ]);
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        return Inertia::render('Tasks/Show', [
            'task' => $task->load([
                'meeting:id,title',
                'assignee:id,name,email',
                'creator:id,name',
                'unit:id,name',
                'actionItem',
                'activities.user:id,name',
                'dispositions.fromUser:id,name',
                'dispositions.toUser:id,name',
                'evidences.user:id,name',
                'evidences.attachments',
            ]),
            'statuses' => Task::STATUSES,
            'unitUsers' => User::where('unit_id', $task->unit_id)->get(['id', 'name']),
            'canApprove' => Auth::user()->can('approve', $task),
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

        $task = Task::create([
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

        $this->activityLogger->log($meeting, Auth::user(), 'task.created', Auth::user()->name . " membuat Task \"{$task->title}\" dari Action Item.", $task);
        $this->webhookDispatcher->dispatch('task.created', $task, ['task_id' => $task->id, 'title' => $task->title, 'status' => $task->status]);

        return back()->with('success', 'Action item berhasil dijadikan Task.');
    }

    public function updateStatus(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        // "Done" hanya lewat approve() (persetujuan), "Review" hanya lewat
        // submitForReview() (wajib lampirkan bukti pengerjaan) — dropdown status
        // biasa cuma untuk transisi antar status kerja yang tidak butuh bukti.
        $selectableStatuses = array_values(array_diff(Task::STATUSES, ['Done', 'Review']));

        $validated = $request->validate([
            'status' => ['required', Rule::in($selectableStatuses)],
        ], [
            'status.in' => 'Status "Done" hanya bisa dicapai lewat persetujuan, dan "Review" hanya bisa diajukan lewat "Ajukan untuk Review" dengan bukti pengerjaan.',
        ]);

        $oldStatus = $task->status;
        $task->update($validated);

        $description = Auth::user()->name . " mengubah status Task \"{$task->title}\" dari {$oldStatus} menjadi {$validated['status']}.";

        $this->activityLogger->log($task->meeting, Auth::user(), 'task.status_changed', $description, $task);
        $this->webhookDispatcher->dispatch('task.status_changed', $task, ['task_id' => $task->id, 'title' => $task->title, 'old_status' => $oldStatus, 'new_status' => $task->status]);

        return back()->with('success', 'Status task berhasil diperbarui.');
    }

    /**
     * Ajukan Task untuk direview — satu-satunya jalan resmi menuju status "Review",
     * wajib melampirkan bukti pengerjaan (catatan dan/atau file) supaya approver
     * (TaskController::approve/reject) punya dasar untuk menilai, bukan cuma
     * klaim tanpa bukti.
     */
    public function submitForReview(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        if (in_array($task->status, ['Done', 'Cancelled', 'Review'], true)) {
            return back()->with('error', 'Task tidak bisa diajukan untuk review dari status saat ini.');
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
        ]);

        if (empty($validated['note']) && empty($request->file('attachments'))) {
            return back()->withErrors(['note' => 'Lampirkan catatan atau file bukti pengerjaan.'])->withInput();
        }

        $oldStatus = $task->status;

        $evidence = $task->evidences()->create([
            'user_id' => Auth::id(),
            'note' => $validated['note'] ?? null,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $evidence->attachments()->create([
                'file_path' => $file->store('task_evidence', 'public'),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
            ]);
        }

        $task->update(['status' => 'Review']);

        $this->activityLogger->log(
            $task->meeting,
            Auth::user(),
            'task.approval_requested',
            Auth::user()->name . " mengajukan Task \"{$task->title}\" untuk direview dengan bukti pengerjaan.",
            $task,
        );
        $this->webhookDispatcher->dispatch('task.status_changed', $task, ['task_id' => $task->id, 'title' => $task->title, 'old_status' => $oldStatus, 'new_status' => 'Review']);

        return back()->with('success', 'Task diajukan untuk direview.');
    }

    public function approve(Task $task)
    {
        $this->authorize('approve', $task);

        if ($task->status !== 'Review') {
            return back()->with('error', 'Task hanya bisa disetujui saat berstatus Review.');
        }

        $task->update(['status' => 'Done']);

        $this->activityLogger->log(
            $task->meeting,
            Auth::user(),
            'task.approved',
            Auth::user()->name . " menyetujui Task \"{$task->title}\" sebagai selesai.",
            $task,
        );
        $this->webhookDispatcher->dispatch('task.approved', $task, ['task_id' => $task->id, 'title' => $task->title]);

        return back()->with('success', 'Task disetujui sebagai selesai.');
    }

    public function reject(Request $request, Task $task)
    {
        $this->authorize('approve', $task);

        if ($task->status !== 'Review') {
            return back()->with('error', 'Task hanya bisa ditolak saat berstatus Review.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $task->update(['status' => 'In Progress']);

        $description = Auth::user()->name . " menolak penyelesaian Task \"{$task->title}\", dikembalikan ke In Progress.";
        if (!empty($validated['reason'])) {
            $description .= " Alasan: {$validated['reason']}";
        }

        $this->activityLogger->log($task->meeting, Auth::user(), 'task.rejected', $description, $task);

        return back()->with('success', 'Task dikembalikan untuk diperbaiki.');
    }

    public function updateSla(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'sla_hours' => 'nullable|integer|min:1|max:8760',
        ]);

        $task->update($validated);

        $description = $validated['sla_hours']
            ? Auth::user()->name . " mengatur SLA Task \"{$task->title}\" menjadi {$validated['sla_hours']} jam."
            : Auth::user()->name . " menghapus SLA Task \"{$task->title}\".";

        $this->activityLogger->log($task->meeting, Auth::user(), 'task.sla_updated', $description, $task);

        return back()->with('success', 'SLA task berhasil diperbarui.');
    }
}
