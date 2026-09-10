<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Services\Analytics\DashboardInsightGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Never call a real LLM from these tests.
        $this->mock(DashboardInsightGenerator::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn('insight singkat.');
        });
    }

    private function user(): User
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id, 'email_verified_at' => now()]);
        $user->syncRoles(['user']);

        return $user;
    }

    public function test_ai_endpoint_returns_429_with_a_localized_json_error_when_over_the_limit(): void
    {
        config(['ai.rate_limits.per_minute' => 1]);
        $user = $this->user();

        $this->actingAs($user)->postJson(route('dashboard.insight'))->assertOk();
        $response = $this->actingAs($user)->postJson(route('dashboard.insight'));

        $response->assertStatus(429);
        $this->assertStringContainsString('Terlalu banyak permintaan', $response->json('error'));
    }

    public function test_inertia_ai_request_over_the_limit_redirects_back_with_a_flash_error(): void
    {
        config(['ai.rate_limits.per_minute' => 1]);
        $user = $this->user();

        $this->actingAs($user)->post(route('dashboard.insight'), [], ['X-Inertia' => 'true']);

        $response = $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('dashboard.insight'), [], ['X-Inertia' => 'true']);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_the_limit_is_per_user(): void
    {
        config(['ai.rate_limits.per_minute' => 1]);

        $userA = $this->user();
        $userB = $this->user();

        $this->actingAs($userA)->postJson(route('dashboard.insight'))->assertOk();
        $this->actingAs($userA)->postJson(route('dashboard.insight'))->assertStatus(429);

        // A different user still has their own budget.
        $this->actingAs($userB)->postJson(route('dashboard.insight'))->assertOk();
    }
}
