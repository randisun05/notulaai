<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
use App\Support\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Tanya lintas rapat: satu pertanyaan atas semua rapat yang boleh dilihat user
 * (unit sendiri; pimpinan/superadmin semua unit). Pencarian kata kunci memilih
 * beberapa rapat paling relevan, lalu AI menjawab HANYA dari rangkuman, keputusan,
 * tindak lanjut, dan kutipan transkrip rapat-rapat itu, dengan rujukan [n].
 */
class CrossMeetingQaService
{
    /** Rapat yang dikirim ke AI sebagai sumber. */
    public const MAX_SOURCES = 6;

    /** Kandidat yang dinilai (terbaru dulu) setelah disaring SQL. */
    private const MAX_CANDIDATES = 300;

    private const SUMMARY_CHARS = 2500;

    private const EXCERPT_CHARS = 2500;

    public function __construct(
        private readonly AiManager $ai,
        private readonly AiRequestLogger $logger,
        private readonly TranscriptRetriever $retriever,
    ) {}

    /**
     * @param  array{unit_id?: ?int, from?: ?string, to?: ?string}  $filters
     * @return array{answer: string, sources: list<array{n: int, id: int, title: string, date: string, unit: ?string, cited: bool}>}
     */
    public function ask(User $user, string $question, array $filters = []): array
    {
        $sources = $this->relevantMeetings($user, $question, $filters);

        if ($sources->isEmpty()) {
            return ['answer' => 'Belum ada notula rapat (yang sudah selesai diproses) dalam cakupan Anda untuk menjawab pertanyaan ini.', 'sources' => []];
        }

        $prompt = $this->prompt($question, $sources);
        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('cross_meeting_qa', $provider, $model, $prompt, $e->getMessage(), null, $user);
            throw $e;
        }

        $this->logger->logSuccess('cross_meeting_qa', $result->provider, $result->model, $prompt, $result->content, $result->promptTokens, $result->completionTokens, $result->durationMs, null, $user);

        preg_match_all('/\[(\d+)\]/', $result->content, $m);
        $cited = array_map('intval', $m[1]);

        return [
            'answer' => trim($result->content),
            'sources' => $sources->values()->map(fn (Meeting $meeting, int $i) => [
                'n' => $i + 1,
                'id' => $meeting->id,
                'title' => $meeting->title,
                'date' => (string) $meeting->date,
                'unit' => $meeting->unit?->name,
                'cited' => in_array($i + 1, $cited, true),
            ])->all(),
        ];
    }

    /**
     * @param  array{unit_id?: ?int, from?: ?string, to?: ?string}  $filters
     * @return Collection<int, Meeting>
     */
    public function relevantMeetings(User $user, string $question, array $filters = []): Collection
    {
        $terms = $this->retriever->terms($question);

        $query = Meeting::query()
            ->visibleTo($user)
            ->where('status', 'Selesai Diproses')
            ->when(($filters['unit_id'] ?? null) && $user->seesAllUnits(), fn ($q) => $q->where('unit_id', $filters['unit_id']))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('date', '<', Carbon::parse($to)->addDay()->toDateString()));

        if ($terms === []) {
            // Pertanyaan umum ("apa saja yang dibahas bulan ini?") → rapat terbaru.
            $candidates = (clone $query)->orderByDesc('date')->limit(self::MAX_SOURCES)->get(['id', 'title', 'date', 'unit_id', 'summary']);

            return $candidates->load(['unit:id,name', 'decisions.actionItem.task', 'actionItems.task']);
        }

        $matches = fn (Builder $q, string $column) => $q->where(function ($q) use ($terms, $column) {
            foreach ($terms as $term) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });

        $candidates = (clone $query)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $matches($q, 'title'))
                ->orWhere(fn ($q) => $matches($q, 'summary'))
                ->orWhere(fn ($q) => $matches($q, 'transcript'))
                ->orWhereHas('decisions', fn ($d) => $matches($d, 'text')))
            ->orderByDesc('date')
            ->limit(self::MAX_CANDIDATES)
            ->get(['id', 'title', 'date', 'unit_id', 'summary'])
            ->load('decisions');

        // Transkrip tidak dimuat untuk semua kandidat (bisa puluhan ribu karakter per rapat);
        // cukup tahu rapat mana yang transkripnya menyebut kata kunci.
        $transcriptHits = $candidates->isEmpty() ? collect() : Meeting::query()
            ->whereIn('id', $candidates->pluck('id'))
            ->where(fn ($q) => $matches($q, 'transcript'))
            ->pluck('id')
            ->flip();

        return $candidates
            ->map(function (Meeting $meeting) use ($terms, $transcriptHits) {
                $meeting->setAttribute('relevance', $this->retriever->score($meeting->title, $terms) * 3
                    + $this->retriever->score(HtmlSanitizer::toText($meeting->summary), $terms) * 2
                    + $this->retriever->score($meeting->decisions->pluck('text')->implode("\n"), $terms) * 2
                    + ($transcriptHits->has($meeting->id) ? 5 : 0));

                return $meeting;
            })
            ->sortByDesc('relevance')
            ->take(self::MAX_SOURCES)
            ->values()
            ->load(['unit:id,name', 'decisions.actionItem.task', 'actionItems.task']);
    }

    /**
     * @param  Collection<int, Meeting>  $sources
     */
    private function prompt(string $question, Collection $sources): string
    {
        $blocks = $sources->values()->map(function (Meeting $meeting, int $i) use ($question) {
            $n = $i + 1;
            $date = Carbon::parse($meeting->date)->locale('id')->isoFormat('dddd, D MMMM Y');
            $summary = mb_substr(HtmlSanitizer::toText($meeting->summary), 0, self::SUMMARY_CHARS);
            $decisions = $meeting->decisions->map(fn (MeetingDecision $d) => '- '.$d->text.' (tindak lanjut: '.$d->follow_up['label'].')')->implode("\n");
            $followUps = $meeting->actionItems->map(fn ($item) => '- '.$item->title
                .($item->assignee_name ? " (PIC: {$item->assignee_name})" : '')
                .($item->deadline ? ' (tenggat '.$item->deadline->toDateString().')' : '')
                .' [status: '.($item->task->status ?? 'belum jadi Task').']')->implode("\n");
            $transcript = (string) Meeting::whereKey($meeting->id)->value('transcript');
            $excerpt = $transcript === '' ? '' : $this->retriever->relevantExcerpt($transcript, $question, self::EXCERPT_CHARS);

            return "=== [{$n}] {$meeting->title} — {$date}".($meeting->unit ? " — {$meeting->unit->name}" : '')." ===\n"
                ."Rangkuman:\n{$summary}\n"
                .($decisions !== '' ? "Keputusan:\n{$decisions}\n" : '')
                .($followUps !== '' ? "Tindak lanjut:\n{$followUps}\n" : '')
                .($excerpt !== '' ? "Kutipan transkrip:\n{$excerpt}\n" : '');
        })->implode("\n");

        $today = now()->locale('id')->isoFormat('D MMMM Y');

        return <<<PROMPT
        Anda asisten notula sebuah instansi pemerintah. Jawab pertanyaan pengguna dalam Bahasa Indonesia yang lugas,
        HANYA berdasarkan SUMBER RAPAT di bawah (hari ini {$today}).
        - Setiap fakta wajib diberi rujukan nomor sumber dalam kurung siku, mis. [1] atau [2][3], dan sebutkan tanggal
          rapatnya bila relevan (mis. "pada rapat 3 September 2026 [2]").
        - Bila beberapa rapat membahas hal yang sama, jelaskan perkembangannya secara kronologis dan sebutkan
          keputusan terakhir.
        - Bila jawabannya tidak ada di sumber, katakan terus terang bahwa tidak ditemukan di notula rapat yang tersedia.
          Jangan mengarang nama, angka, atau keputusan.
        - Teks biasa tanpa markdown tebal/judul; boleh daftar dengan tanda "-".

        Pertanyaan: {$question}

        SUMBER RAPAT:
        {$blocks}
        PROMPT;
    }
}
