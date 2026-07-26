<?php

namespace App\Services\Meeting;

use App\Models\Activity;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;

class ActivityLogger
{
    public function log(?Meeting $meeting, ?User $user, string $type, string $description, ?Task $task = null): void
    {
        Activity::create([
            'meeting_id' => $meeting?->id,
            'task_id' => $task?->id,
            'user_id' => $user?->id,
            'type' => $type,
            'description' => $description,
        ]);
    }
}
