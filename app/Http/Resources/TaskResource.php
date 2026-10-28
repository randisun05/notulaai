<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'deadline' => $this->deadline?->toDateString(),
            'sla_hours' => $this->sla_hours,
            'is_overdue' => $this->is_overdue,
            'is_sla_breached' => $this->is_sla_breached,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new UserSummaryResource($this->assignee) : null),
            // Free-text PIC from the AI action item, when no user account is linked.
            'assignee_name' => $this->assignee_name,
            'meeting' => $this->whenLoaded('meeting', fn () => $this->meeting ? ['id' => $this->meeting->id, 'title' => $this->meeting->title] : null),
            'unit_id' => $this->unit_id,
            'url' => route('tasks.show', $this->id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
