<?php

namespace Tests\Feature\Admin;

use App\Models\AiRequestLog;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiUsageTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unitA;

    private Unit $unitB;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->unitA = Unit::factory()->create(['name' => 'Unit A']);
        $this->unitB = Unit::factory()->create(['name' => 'Unit B']);
        $this->superadmin = User::factory()->create(['unit_id' => $this->unitA->id]);
        $this->superadmin->syncRoles(['superadmin']);
    }

    private function log(array $attributes): AiRequestLog
    {
        $log = AiRequestLog::create(array_merge([
            'type' => 'text',
            'provider' => 'gemini',
            'model' => 'gemini-flash-latest',
            'status' => 'success',
        ], $attributes));

        if (isset($attributes['created_at'])) {
            $log->forceFill(['created_at' => $attributes['created_at']])->save();
        }

        return $log;
    }

    private function props(array $query = []): array
    {
        $props = [];

        $this->actingAs($this->superadmin)
            ->get(route('admin.ai-usage.index', $query))
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $page->component('Admin/AiUsage/Index');
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    public function test_only_superadmin_can_open_the_page(): void
    {
        $user = User::factory()->create(['unit_id' => $this->unitA->id]);
        $user->syncRoles(['user']);

        $this->actingAs($user)->get(route('admin.ai-usage.index'))->assertForbidden();
    }

    public function test_totals_count_requests_failures_tokens_and_cost(): void
    {
        $this->log(['prompt_tokens' => 1000, 'completion_tokens' => 200, 'cost' => 0.0008, 'duration_ms' => 1000]);
        $this->log(['prompt_tokens' => 500, 'completion_tokens' => 100, 'cost' => 0.0002, 'duration_ms' => 3000]);
        $this->log(['status' => 'failed', 'error_message' => 'quota']);

        $totals = $this->props()['totals'];

        $this->assertSame(3, $totals['requests']);
        $this->assertSame(1, $totals['failed']);
        $this->assertSame(1800, $totals['tokens']);
        $this->assertEqualsWithDelta(0.001, $totals['cost'], 1e-9);
        $this->assertSame(0, $totals['unpriced']);
        $this->assertSame(2000, $totals['avg_duration_ms']);
    }

    public function test_successful_calls_without_a_price_are_counted_as_unpriced(): void
    {
        $this->log(['prompt_tokens' => 10, 'completion_tokens' => 10, 'cost' => null]);
        $this->log(['cost' => 0.5]);

        $totals = $this->props()['totals'];

        $this->assertSame(1, $totals['unpriced']);
        $this->assertEqualsWithDelta(0.5, $totals['cost'], 1e-9);
    }

    public function test_period_filter_excludes_older_logs_and_daily_series_is_zero_filled(): void
    {
        $this->log(['created_at' => Carbon::now()->subDays(2)]);
        $this->log(['created_at' => Carbon::now()->subDays(20)]);

        $props = $this->props(['days' => 7]);

        $this->assertSame(7, $props['days']);
        $this->assertSame(1, $props['totals']['requests']);
        $this->assertCount(7, $props['daily']);
        $this->assertSame(Carbon::now()->toDateString(), end($props['daily'])['day']);
        $this->assertSame(1, array_sum(array_column($props['daily'], 'requests')));
    }

    public function test_invalid_period_falls_back_to_thirty_days(): void
    {
        $this->assertSame(30, $this->props(['days' => 9999])['days']);
    }

    public function test_usage_is_attributed_to_the_meeting_unit_or_else_the_users_unit(): void
    {
        $userB = User::factory()->create(['unit_id' => $this->unitB->id]);
        $meetingA = Meeting::create([
            'user_id' => $userB->id, 'unit_id' => $this->unitA->id, 'title' => 'Rapat A',
            'date' => now(), 'agenda' => '-', 'attendees' => '-', 'status' => 'Selesai Diproses',
        ]);

        $this->log(['meeting_id' => $meetingA->id, 'user_id' => $userB->id]); // meeting wins → Unit A
        $this->log(['user_id' => $userB->id, 'type' => 'dashboard_insight']); // no meeting → Unit B
        $this->log(['user_id' => $userB->id, 'type' => 'chat']);              // → Unit B

        $byUnit = collect($this->props()['byUnit'])->pluck('requests', 'unit_name')->all();

        $this->assertSame(['Unit B' => 2, 'Unit A' => 1], $byUnit);
    }

    public function test_breakdowns_by_provider_and_type_and_recent_failures(): void
    {
        $this->log(['provider' => 'gemini']);
        $this->log(['provider' => 'gemini', 'status' => 'failed', 'error_message' => 'Semua API key Gemini gagal']);
        $this->log(['provider' => 'openrouter', 'model' => 'openai/gpt-oss-20b:free', 'type' => 'chat', 'cost' => 0]);

        $props = $this->props();

        $gemini = collect($props['byProvider'])->firstWhere('provider', 'gemini');
        $this->assertSame(2, $gemini['requests']);
        $this->assertSame(1, $gemini['failed']);

        $this->assertEqualsCanonicalizing(['text', 'chat'], array_column($props['byType'], 'type'));

        $this->assertCount(1, $props['recentFailures']);
        $this->assertSame('Semua API key Gemini gagal', $props['recentFailures'][0]['error_message']);
    }
}
