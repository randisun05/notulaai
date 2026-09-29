<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingDecision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Rapat berseri: rapat bisa ditandai sebagai lanjutan rapat sebelumnya
 * (meetings.previous_meeting_id). Tindak lanjut yang belum selesai dan keputusan
 * dari rapat-rapat sebelumnya dalam seri ikut tampil dan masuk ke konteks AI.
 */
class MeetingSeriesService
{
    /** Seberapa jauh ke belakang seri ditelusuri. */
    public const MAX_DEPTH = 10;

    /**
     * Rapat-rapat sebelumnya dalam seri, terdekat dulu.
     *
     * @return Collection<int, Meeting>
     */
    public function ancestors(Meeting $meeting): Collection
    {
        $chain = collect();
        $seen = [$meeting->id => true];
        $current = $meeting;

        while ($current->previous_meeting_id && $chain->count() < self::MAX_DEPTH) {
            $previous = Meeting::find($current->previous_meeting_id);
            if (! $previous || isset($seen[$previous->id])) {
                break;
            }
            $seen[$previous->id] = true;
            $chain->push($previous);
            $current = $previous;
        }

        return $chain;
    }

    /**
     * Data "memori" untuk halaman rapat.
     *
     * @return array{previous: list<array<string, mixed>>, next: list<array<string, mixed>>, open_follow_ups: list<array<string, mixed>>, decisions: list<array<string, mixed>>}|null
     */
    public function memory(Meeting $meeting): ?array
    {
        $ancestors = $this->ancestors($meeting);
        $next = $meeting->followUpMeetings()->get(['id', 'title', 'date', 'status']);

        if ($ancestors->isEmpty() && $next->isEmpty()) {
            return null;
        }

        $brief = fn (Meeting $m) => ['id' => $m->id, 'title' => $m->title, 'date' => $m->date, 'status' => $m->status];

        return [
            'previous' => $ancestors->map($brief)->values()->all(),
            'next' => $next->map($brief)->values()->all(),
            'open_follow_ups' => $this->openFollowUps($ancestors)->map(fn (MeetingActionItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'assignee' => $item->task->assignee_name ?? $item->assignee_name,
                'deadline' => ($item->task->deadline ?? $item->deadline)?->toDateString(),
                'overdue' => (bool) $item->task?->is_overdue,
                'task_id' => $item->task?->id,
                'status' => $item->task->status ?? 'Belum jadi Task',
                'meeting' => ['id' => $item->meeting->id, 'title' => $item->meeting->title, 'date' => $item->meeting->date],
            ])->values()->all(),
            'decisions' => $this->decisions($ancestors)->map(fn (MeetingDecision $d) => [
                'id' => $d->id,
                'text' => $d->text,
                'follow_up' => $d->follow_up,
                'meeting' => ['id' => $d->meeting->id, 'title' => $d->meeting->title, 'date' => $d->meeting->date],
            ])->values()->all(),
        ];
    }

    /**
     * Konteks untuk prompt AI (rangkuman, notulen): tindak lanjut & keputusan dari
     * rapat sebelumnya. Kosong kalau rapat ini bukan lanjutan.
     */
    public function promptContext(Meeting $meeting): string
    {
        $ancestors = $this->ancestors($meeting);
        if ($ancestors->isEmpty()) {
            return '';
        }

        $date = fn (Meeting $m) => Carbon::parse($m->date)->locale('id')->isoFormat('D MMMM Y');

        $lines = ['Rapat ini adalah lanjutan dari: '.$ancestors->map(fn (Meeting $m) => "\"{$m->title}\" ({$date($m)})")->implode('; ').'.'];

        $open = $this->openFollowUps($ancestors);
        if ($open->isNotEmpty()) {
            $lines[] = 'Tindak lanjut dari rapat sebelumnya yang BELUM selesai:';
            foreach ($open as $item) {
                $lines[] = '- '.$item->title
                    .(($item->task->assignee_name ?? $item->assignee_name) ? ' (PIC: '.($item->task->assignee_name ?? $item->assignee_name).')' : '')
                    .(($deadline = $item->task->deadline ?? $item->deadline) ? ' (tenggat '.$deadline->toDateString().')' : '')
                    .' [status: '.($item->task->status ?? 'belum ditugaskan').'; dari rapat '.$date($item->meeting).']';
            }
        }

        $decisions = $this->decisions($ancestors)->take(15);
        if ($decisions->isNotEmpty()) {
            $lines[] = 'Keputusan rapat sebelumnya:';
            foreach ($decisions as $decision) {
                $lines[] = '- '.$decision->text.' [rapat '.$date($decision->meeting).']';
            }
        }

        $lines[] = 'If this transcript reports progress on any of these follow-ups or revisits these decisions, include a section '
            .'"Perkembangan Tindak Lanjut Rapat Sebelumnya" stating what was reported. Only report what the transcript says; '
            .'never invent progress.';

        return implode("\n", $lines)."\n\n";
    }

    /**
     * Rapat yang boleh dipilih sebagai "lanjutan dari" (unit yang sama, terbaru dulu).
     *
     * @return Collection<int, Meeting>
     */
    public function candidates(?int $unitId, ?Meeting $except = null): Collection
    {
        return Meeting::query()
            ->where('unit_id', $unitId)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->orderByDesc('date')
            ->limit(100)
            ->get(['id', 'title', 'date']);
    }

    /**
     * Aturan validasi previous_meeting_id: rapat unit yang sama, bukan diri sendiri,
     * dan tidak membuat seri berputar (rapat lanjutannya sendiri tidak boleh dipilih).
     *
     * @return list<mixed>
     */
    public function rules(?int $unitId, ?Meeting $meeting = null): array
    {
        return ['nullable', 'integer', Rule::exists('meetings', 'id')->where('unit_id', $unitId),
            function (string $attribute, mixed $value, \Closure $fail) use ($meeting) {
                if (! $meeting || ! $value) {
                    return;
                }
                $candidate = Meeting::find($value);
                if ((int) $value === $meeting->id || ($candidate && $this->ancestors($candidate)->contains('id', $meeting->id))) {
                    $fail('Rapat tersebut adalah lanjutan dari rapat ini, jadi tidak bisa dipilih sebagai rapat sebelumnya.');
                }
            }];
    }

    /**
     * @param  Collection<int, Meeting>  $ancestors
     * @return Collection<int, MeetingActionItem>
     */
    private function openFollowUps(Collection $ancestors): Collection
    {
        if ($ancestors->isEmpty()) {
            return collect();
        }

        return MeetingActionItem::query()
            ->whereIn('meeting_id', $ancestors->pluck('id'))
            ->with(['task', 'meeting:id,title,date'])
            ->get()
            ->reject(fn (MeetingActionItem $item) => in_array($item->task?->status, ['Done', 'Cancelled'], true))
            ->sortBy(fn (MeetingActionItem $item) => [$ancestors->search(fn ($m) => $m->id === $item->meeting_id), $item->order])
            ->values();
    }

    /**
     * @param  Collection<int, Meeting>  $ancestors
     * @return Collection<int, MeetingDecision>
     */
    private function decisions(Collection $ancestors): Collection
    {
        if ($ancestors->isEmpty()) {
            return collect();
        }

        return MeetingDecision::query()
            ->whereIn('meeting_id', $ancestors->pluck('id'))
            ->with(['meeting:id,title,date', 'actionItem.task'])
            ->get()
            ->sortBy(fn (MeetingDecision $d) => [$ancestors->search(fn ($m) => $m->id === $d->meeting_id), $d->order])
            ->values();
    }

    /** Dipakai halaman pembuatan rapat: `?previous=ID` hanya kalau rapat itu terlihat & se-unit. */
    public function prefill(User $user, mixed $previousId): ?int
    {
        $previous = $previousId ? Meeting::find($previousId) : null;

        return $previous && $previous->unit_id === $user->unit_id ? $previous->id : null;
    }
}
