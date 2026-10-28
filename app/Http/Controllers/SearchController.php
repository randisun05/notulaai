<?php

namespace App\Http\Controllers;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * One search box over meetings, tasks and forum comments. Everything is
 * scoped exactly like the list pages: visibleTo() for meetings and tasks,
 * and comments only on meetings the user can see.
 */
class SearchController extends Controller
{
    public const MIN_LENGTH = 2;

    /** Results page: up to 20 per group. */
    public function index(Request $request)
    {
        $q = $this->term($request);

        return Inertia::render('Search/Index', [
            'q' => $q,
            'results' => $q === null ? null : $this->search($request->user(), $q, 20),
        ]);
    }

    /** Command-palette results: up to 5 per group. */
    public function quick(Request $request): JsonResponse
    {
        $q = $this->term($request);

        return response()->json($q === null
            ? ['meetings' => [], 'tasks' => [], 'comments' => []]
            : $this->search($request->user(), $q, 5));
    }

    private function term(Request $request): ?string
    {
        $q = trim((string) $request->query('q', ''));

        return mb_strlen($q) >= self::MIN_LENGTH ? Str::limit($q, 100, '') : null;
    }

    /** @return array{meetings: array, tasks: array, comments: array} */
    private function search(User $user, string $q, int $limit): array
    {
        $meetings = Meeting::query()
            ->visibleTo($user)
            ->search($q)
            ->orderByDesc('date')
            ->limit($limit)
            ->get(['id', 'title', 'date', 'status', 'agenda', 'summary'])
            ->map(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'subtitle' => trim(collect([
                    $m->date ? Carbon::parse($m->date)->locale('id')->translatedFormat('j M Y') : null,
                    $m->status,
                ])->filter()->implode(' · ')),
                'snippet' => $this->snippet($m->summary ?: $m->agenda, $q),
                'url' => route('meetings.show', $m->id),
            ]);

        $tasks = Task::query()
            ->visibleTo($user)
            ->search($q)
            ->orderByRaw("CASE WHEN status IN ('Done', 'Cancelled') THEN 1 ELSE 0 END")
            ->latest()
            ->limit($limit)
            ->get(['id', 'title', 'description', 'status', 'deadline', 'assignee_name'])
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'subtitle' => collect([$t->status, $t->assignee_name ? "PIC: {$t->assignee_name}" : null])->filter()->implode(' · '),
                'snippet' => $this->snippet($t->description, $q),
                'url' => route('tasks.show', $t->id),
            ]);

        $comments = ForumComment::query()
            ->visibleSearch($user, $q)
            ->with(['user:id,name', 'meeting:id,title'])
            ->latest()
            ->limit($limit)
            ->get(['id', 'body', 'user_id', 'meeting_id', 'created_at'])
            ->map(fn (ForumComment $c) => [
                'id' => $c->id,
                'title' => $c->meeting?->title ?? 'Rapat',
                'subtitle' => $c->user?->name,
                'snippet' => $this->snippet($c->body, $q),
                'url' => route('meetings.show', $c->meeting_id).'#forum',
            ]);

        return ['meetings' => $meetings->all(), 'tasks' => $tasks->all(), 'comments' => $comments->all()];
    }

    /**
     * Plain-text excerpt around the first match (HTML stripped). Rendered with
     * {{ }} in Vue, so it is never interpreted as markup.
     */
    private function snippet(?string $text, string $q, int $radius = 60): ?string
    {
        if (! $text) {
            return null;
        }

        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br', '</p>', '</li>'], [' <br', ' </p>', ' </li>'], $text)))));
        $pos = mb_stripos($plain, $q);

        if ($pos === false) {
            return Str::limit($plain, $radius * 2);
        }

        $start = max(0, $pos - $radius);
        $excerpt = mb_substr($plain, $start, mb_strlen($q) + $radius * 2);

        return ($start > 0 ? '…' : '').$excerpt.($start + mb_strlen($excerpt) < mb_strlen($plain) ? '…' : '');
    }
}
