<?php

namespace App\Services\Meeting;

use App\Models\Activity;
use App\Models\Meeting;
use App\Models\User;

class ActivityLogger
{
    public function log(Meeting $meeting, ?User $user, string $type, string $description): void
    {
        Activity::create([
            'meeting_id' => $meeting->id,
            'user_id' => $user?->id,
            'type' => $type,
            'description' => $description,
        ]);
    }
}
