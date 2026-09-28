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
            'unit_id' => $this->unit_id,
            'meeting_id' => $this->meeting_id,
            'assignee' => $this->assignee_id
                ? ['id' => $this->assignee_id, 'name' => $this->assignee->name ?? $this->assignee_name]
                : ($this->assignee_name ? ['id' => null, 'name' => $this->assignee_name] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
