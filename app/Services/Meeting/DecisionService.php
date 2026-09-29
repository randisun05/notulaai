<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Models\MeetingMinutes;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Register keputusan lintas rapat. Sumbernya:
 *  1. AI, dari rangkuman + penanda "Keputusan" + action items, saat notula selesai
 *     diproses (sekaligus mengaitkan tiap keputusan dengan action item yang
 *     menjalankannya → status tindak lanjut ikut Task-nya);
 *  2. "Kesimpulan rapat" notulen resmi yang disahkan — menggantikan hasil AI, karena
 *     itulah rumusan resminya.
 */
class DecisionService
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly AiRequestLogger $logger,
    ) {}

    /**
     * Ekstrak keputusan dengan AI. Best-effort: gagal = keputusan lama dibiarkan.
     * Tidak menyentuh keputusan yang sudah dari notulen resmi.
     *
     * @return int jumlah keputusan tersimpan
     */
    public function extract(Meeting $meeting, ?User $user = null): int
    {
        if ($meeting->decisions()->where('source', MeetingDecision::SOURCE_MINUTES)->exists()) {
            return $meeting->decisions()->count();
        }

        $actionItems = $meeting->actionItems()->get()->values();
        $markers = $meeting->markers()->where('type', 'keputusan')->get()->map->describe()->implode("\n");
        $summary = HtmlSanitizer::toText($meeting->summary);
        if ($summary === '' && $markers === '') {
            return 0;
        }

        $numbered = $actionItems->map(fn ($item, $i) => ($i + 1).'. '.$item->title)->implode("\n");

        $prompt = <<<PROMPT
        Dari data rapat berikut, susun daftar KEPUTUSAN / KESIMPULAN yang benar-benar disepakati dalam rapat
        (bukan sekadar topik yang dibahas, bukan usulan yang belum disepakati). Tulis tiap keputusan sebagai satu
        kalimat lugas dalam Bahasa Indonesia baku yang bisa dipahami tanpa membaca notulanya (sebut objeknya,
        angka, dan tenggat bila ada). Jangan mengarang.

        Untuk tiap keputusan, bila ada tindak lanjut di daftar bernomor di bawah yang menjalankan keputusan itu,
        isi "tindak_lanjut" dengan nomornya; selain itu null.

        Balas HANYA dengan JSON array valid tanpa markdown:
        [{"keputusan": string, "tindak_lanjut": number|null}, ...]
        Bila tidak ada keputusan yang jelas, balas [].

        Judul rapat: {$meeting->title}

        Keputusan yang ditandai notulis saat rapat (wajib dimasukkan):
        {$markers}

        Tindak lanjut:
        {$numbered}

        Rangkuman rapat:
        {$summary}
        PROMPT;

        $provider = $this->ai->activeTextProvider();
        $model = Config::get("ai.providers.{$provider}.model");
        $user ??= $meeting->creator;

        try {
            $result = $this->ai->text()->generate($prompt);
        } catch (Throwable $e) {
            $this->logger->logFailure('decisions', $provider, $model, $prompt, $e->getMessage(), $meeting, $user);
            Log::warning("Gagal mengekstrak keputusan Rapat ID {$meeting->id}: ".$e->getMessage());

            return $meeting->decisions()->count();
        }

        $this->logger->logSuccess('decisions', $result->provider, $result->model, $prompt, $result->content, $result->promptTokens, $result->completionTokens, $result->durationMs, $meeting, $user);

        $parsed = $this->parse($result->content);
        if ($parsed === null) {
            Log::warning("Respons keputusan Rapat ID {$meeting->id} bukan JSON yang valid, dilewati.");

            return $meeting->decisions()->count();
        }

        $rows = collect($parsed)->map(fn (array $d) => [
            'text' => $d['text'],
            'meeting_action_item_id' => $d['follow_up'] !== null ? $actionItems->get($d['follow_up'] - 1)?->id : null,
        ]);

        $this->replace($meeting, MeetingDecision::SOURCE_AI, $rows->values()->all());

        return $rows->count();
    }

    /**
     * Kesimpulan rapat notulen resmi yang disahkan menggantikan hasil AI. Kaitan ke
     * tindak lanjut diwariskan dari keputusan AI yang rumusannya paling mirip.
     */
    public function syncFromMinutes(MeetingMinutes $minutes): void
    {
        $meeting = $minutes->meeting;
        $previous = $meeting->decisions()->get();

        $rows = collect($minutes->decisions ?? [])
            ->map(fn ($text) => trim((string) $text))
            ->filter()
            ->map(fn (string $text) => [
                'text' => $text,
                'meeting_action_item_id' => $this->closest($text, $previous)?->meeting_action_item_id,
            ])
            ->values();

        if ($rows->isEmpty()) {
            // Notulen tanpa kesimpulan: pertahankan hasil AI daripada mengosongkan register.
            return;
        }

        $this->replace($meeting, MeetingDecision::SOURCE_MINUTES, $rows->all());
    }

    /**
     * @return list<array{text: string, follow_up: ?int}>|null null kalau bukan JSON array
     */
    public function parse(string $raw): ?array
    {
        $json = trim($raw);
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $json, $m)) {
            $json = $m[1];
        }
        $decoded = json_decode($json, true);
        if (! is_array($decoded) && preg_match('/\[.*\]/s', $json, $m)) {
            $decoded = json_decode($m[0], true);
        }
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return null;
        }

        return collect($decoded)
            ->map(fn ($item) => is_string($item) ? ['keputusan' => $item] : $item)
            ->filter(fn ($item) => is_array($item) && is_string($item['keputusan'] ?? null) && trim($item['keputusan']) !== '')
            ->map(fn (array $item) => [
                'text' => trim($item['keputusan']),
                'follow_up' => is_numeric($item['tindak_lanjut'] ?? null) && (int) $item['tindak_lanjut'] > 0 ? (int) $item['tindak_lanjut'] : null,
            ])
            ->unique(fn (array $item) => mb_strtolower($item['text']))
            ->values()
            ->all();
    }

    /**
     * @param  list<array{text: string, meeting_action_item_id: ?int}>  $rows
     */
    private function replace(Meeting $meeting, string $source, array $rows): void
    {
        DB::transaction(function () use ($meeting, $source, $rows) {
            $meeting->decisions()->delete();
            foreach ($rows as $i => $row) {
                $meeting->decisions()->create($row + ['unit_id' => $meeting->unit_id, 'source' => $source, 'order' => $i]);
            }
        });
    }

    /**
     * @param  Collection<int, MeetingDecision>  $candidates
     */
    private function closest(string $text, Collection $candidates): ?MeetingDecision
    {
        $words = $this->words($text);
        $best = null;
        $bestScore = 0.4; // minimal 40% kata sama (Jaccard)

        foreach ($candidates as $candidate) {
            $other = $this->words($candidate->text);
            $union = count(array_unique([...$words, ...$other]));
            $score = $union ? count(array_intersect($words, $other)) / $union : 0;
            if ($score >= $bestScore) {
                [$best, $bestScore] = [$candidate, $score];
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($text), $m);

        return array_values(array_unique($m[0]));
    }
}
