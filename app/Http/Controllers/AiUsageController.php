<?php

namespace App\Http\Controllers;

use App\Models\AiRequestLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Superadmin overview of ai_request_logs: volume, failures, tokens and cost,
 * per day / provider / unit / request type, plus the latest failures.
 */
class AiUsageController extends Controller
{
    public const PERIODS = [7, 30, 90];

    public function index(Request $request)
    {
        $this->authorize('access-admin-panel');

        $days = (int) $request->input('days', 30);
        if (! in_array($days, self::PERIODS, true)) {
            $days = 30;
        }

        $since = Carbon::now()->subDays($days - 1)->startOfDay();
        $base = fn (): Builder => AiRequestLog::query()->where('ai_request_logs.created_at', '>=', $since);

        return Inertia::render('Admin/AiUsage/Index', [
            'days' => $days,
            'periods' => self::PERIODS,
            'totals' => $this->totals($base()),
            'daily' => $this->daily($base(), $since, $days),
            'byProvider' => $this->breakdown($base(), ['provider', 'model']),
            'byType' => $this->breakdown($base(), ['type']),
            'byUnit' => $this->byUnit($base()),
            'recentFailures' => $base()
                ->where('status', 'failed')
                ->with(['user:id,name', 'meeting:id,title'])
                ->latest()
                ->limit(10)
                ->get(['id', 'type', 'provider', 'model', 'error_message', 'user_id', 'meeting_id', 'created_at']),
        ]);
    }

    /** Aggregate columns shared by every breakdown. */
    private function aggregates(): array
    {
        return [
            DB::raw('COUNT(*) as requests'),
            DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
            DB::raw('COALESCE(SUM(prompt_tokens), 0) + COALESCE(SUM(completion_tokens), 0) as tokens'),
            DB::raw('SUM(cost) as cost'),
            // Successful calls without a known price: the cost sum is a lower bound.
            DB::raw("SUM(CASE WHEN status = 'success' AND cost IS NULL THEN 1 ELSE 0 END) as unpriced"),
        ];
    }

    private function totals(Builder $query): array
    {
        $row = $query->select([...$this->aggregates(), DB::raw('AVG(duration_ms) as avg_duration_ms')])->toBase()->first();

        return $this->normalize((array) $row) + ['avg_duration_ms' => $row?->avg_duration_ms !== null ? (int) round($row->avg_duration_ms) : null];
    }

    /** One entry per day in the period, zero-filled, oldest first. */
    private function daily(Builder $query, Carbon $since, int $days): array
    {
        $rows = $query
            ->select([DB::raw('DATE(ai_request_logs.created_at) as day'), ...$this->aggregates()])
            ->groupBy('day')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($since, $rows) {
            $day = $since->copy()->addDays($offset)->toDateString();

            return ['day' => $day] + $this->normalize((array) ($rows[$day] ?? []));
        })->all();
    }

    /** @param array<int, string> $columns */
    private function breakdown(Builder $query, array $columns): array
    {
        return $query
            ->select([...$columns, ...$this->aggregates()])
            ->groupBy($columns)
            ->orderByDesc('requests')
            ->toBase()
            ->get()
            ->map(fn ($row) => array_intersect_key((array) $row, array_flip($columns)) + $this->normalize((array) $row))
            ->all();
    }

    /**
     * A request belongs to its meeting's unit, or else to the requesting
     * user's unit (chat/dashboard insight have a user but maybe no meeting).
     */
    private function byUnit(Builder $query): array
    {
        return $query
            ->leftJoin('meetings', 'meetings.id', '=', 'ai_request_logs.meeting_id')
            ->leftJoin('users', 'users.id', '=', 'ai_request_logs.user_id')
            ->leftJoin('units', 'units.id', '=', DB::raw('COALESCE(meetings.unit_id, users.unit_id)'))
            ->select([
                DB::raw('units.id as unit_id'),
                DB::raw('units.name as unit_name'),
                DB::raw('COUNT(*) as requests'),
                DB::raw("SUM(CASE WHEN ai_request_logs.status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw('COALESCE(SUM(ai_request_logs.prompt_tokens), 0) + COALESCE(SUM(ai_request_logs.completion_tokens), 0) as tokens'),
                DB::raw('SUM(ai_request_logs.cost) as cost'),
                DB::raw("SUM(CASE WHEN ai_request_logs.status = 'success' AND ai_request_logs.cost IS NULL THEN 1 ELSE 0 END) as unpriced"),
            ])
            ->groupBy('units.id', 'units.name')
            ->orderByDesc('requests')
            ->toBase()
            ->get()
            ->map(fn ($row) => ['unit_id' => $row->unit_id, 'unit_name' => $row->unit_name] + $this->normalize((array) $row))
            ->all();
    }

    /** DB drivers return aggregates as strings/null; cast them once here. */
    private function normalize(array $row): array
    {
        return [
            'requests' => (int) ($row['requests'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'tokens' => (int) ($row['tokens'] ?? 0),
            'cost' => isset($row['cost']) ? round((float) $row['cost'], 6) : null,
            'unpriced' => (int) ($row['unpriced'] ?? 0),
        ];
    }
}
