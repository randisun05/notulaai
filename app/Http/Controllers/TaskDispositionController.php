<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\Meeting\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskDispositionController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function store(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'to_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('unit_id', $task->unit_id),
            ],
            'note' => 'nullable|string|max:1000',
        ]);

        $fromUser = Auth::user();
        $toUser = User::findOrFail($validated['to_user_id']);

        $task->dispositions()->create([
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'note' => $validated['note'] ?? null,
        ]);

        $task->update([
            'assignee_id' => $toUser->id,
            'assignee_name' => $toUser->name,
        ]);

        $description = "{$fromUser->name} mendisposisikan Task \"{$task->title}\" ke {$toUser->name}.";
        if (! empty($validated['note'])) {
            $description .= " Catatan: {$validated['note']}";
        }

        $this->activityLogger->log($task->meeting, $fromUser, 'task.disposed', $description, $task);

        return back()->with('success', "Task berhasil didisposisikan ke {$toUser->name}.");
    }
}
