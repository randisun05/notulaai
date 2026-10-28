<?php

namespace App\Http\Resources;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Meeting */
class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'date' => $this->date,
            'status' => $this->status,
            'unit' => $this->whenLoaded('unit', fn () => ['id' => $this->unit?->id, 'name' => $this->unit?->name]),
            'agenda' => $this->agenda,
            'attendees' => $this->attendees,
            // Sanitized HTML (HtmlSanitizer, ai_html profile), same as the web UI renders.
            'summary_html' => $this->summary,
            // Transcripts can be very long: only on the detail endpoint with ?include=transcript.
            'transcript' => $this->when($request->routeIs('api.v1.meetings.show') && $this->includes($request, 'transcript'), $this->transcript),
            'action_items' => ActionItemResource::collection($this->whenLoaded('actionItems')),
            'url' => route('meetings.show', $this->id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function includes(Request $request, string $relation): bool
    {
        return in_array($relation, explode(',', (string) $request->query('include', '')), true);
    }
}
