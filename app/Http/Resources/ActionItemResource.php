<?php

namespace App\Http\Resources;

use App\Models\MeetingActionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MeetingActionItem */
class ActionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'assignee_name' => $this->assignee_name,
            'deadline' => $this->deadline?->toDateString(),
            'converted_to_task' => (bool) $this->converted_to_task,
        ];
    }
}
