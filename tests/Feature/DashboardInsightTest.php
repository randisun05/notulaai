<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardInsightTest extends TestCase
{
    public function test_guest_cannot_request_insight(): void
    {
        $response = $this->postJson(route('dashboard.insight'));

        $response->assertStatus(401);
    }
}
