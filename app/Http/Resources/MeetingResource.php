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
            // Jam lokal (WIB) sesuai yang diinput user, format "YYYY-MM-DD HH:MM:SS".
            'date' => $this->date ? str_replace('T', ' ', (string) $this->date) : null,
            'status' => $this->status,
            'unit_id' => $this->unit_id,
            'agenda' => $this->agenda,
            'attendees' => $this->attendees,
            // Isi lengkap hanya di endpoint detail — daftar tetap ringan.
            'summary' => $this->when($request->routeIs('api.v1.meetings.show'), $this->summary),
            'transcript' => $this->when($request->routeIs('api.v1.meetings.show'), $this->transcript),
            'action_items' => ActionItemResource::collection($this->whenLoaded('actionItems')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
