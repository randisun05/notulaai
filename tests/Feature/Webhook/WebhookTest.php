<?php

namespace Tests\Feature\Webhook;

use App\Jobs\SendWebhookNotification;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Webhook\WebhookDispatcher;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function superadmin(Unit $unit): User
    {
        $superadmin = User::factory()->create(['unit_id' => $unit->id]);
        $superadmin->syncRoles(['superadmin']);

        return $superadmin;
    }

    public function test_only_superadmin_can_manage_webhooks(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $this->actingAs($user)->get(route('admin.webhooks.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.webhooks.store'), [])->assertForbidden();
    }

    public function test_superadmin_can_create_webhook_with_generated_secret(): void
    {
        $unit = Unit::factory()->create();
        $superadmin = $this->superadmin($unit);

        $response = $this->actingAs($superadmin)->post(route('admin.webhooks.store'), [
            'name' => 'Integrasi Slack',
            'unit_id' => $unit->id,
            'url' => 'https://hooks.example.com/notula',
            'events' => ['task.created', 'meeting.processed'],
        ]);

        $response->assertRedirect();
        $webhook = Webhook::first();
        $this->assertNotNull($webhook);
        $this->assertSame('Integrasi Slack', $webhook->name);
        $this->assertNotEmpty($webhook->secret);
        $this->assertSame(['task.created', 'meeting.processed'], $webhook->events);
        $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.created']);
    }

    public function test_superadmin_can_toggle_and_delete_webhook(): void
    {
        $unit = Unit::factory()->create();
        $superadmin = $this->superadmin($unit);
        $webhook = Webhook::create([
            'unit_id' => $unit->id,
            'name' => 'Test', 'url' => 'https://example.com/hook', 'secret' => 'secret', 'events' => ['task.created'], 'is_active' => true,
        ]);

        $this->actingAs($superadmin)->patch(route('admin.webhooks.update', $webhook), ['is_active' => false])->assertRedirect();
        $this->assertFalse($webhook->fresh()->is_active);

        $this->actingAs($superadmin)->delete(route('admin.webhooks.destroy', $webhook))->assertRedirect();
        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    public function test_dispatcher_queues_job_only_for_active_subscribed_webhook_in_same_unit(): void
    {
        Queue::fake();

        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();

        $matching = Webhook::create(['unit_id' => $unit->id, 'name' => 'Match', 'url' => 'https://a.test', 'secret' => 's', 'events' => ['task.created'], 'is_active' => true]);
        Webhook::create(['unit_id' => $unit->id, 'name' => 'Inactive', 'url' => 'https://b.test', 'secret' => 's', 'events' => ['task.created'], 'is_active' => false]);
        Webhook::create(['unit_id' => $unit->id, 'name' => 'Not subscribed', 'url' => 'https://c.test', 'secret' => 's', 'events' => ['meeting.processed'], 'is_active' => true]);
        Webhook::create(['unit_id' => $otherUnit->id, 'name' => 'Other unit', 'url' => 'https://d.test', 'secret' => 's', 'events' => ['task.created'], 'is_active' => true]);

        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task X', 'status' => 'Todo']);

        app(WebhookDispatcher::class)->dispatch('task.created', $task, ['task_id' => $task->id]);

        Queue::assertPushed(SendWebhookNotification::class, 1);
    }

    public function test_send_webhook_notification_posts_signed_payload(): void
    {
        Http::fake();

        $webhook = Webhook::make(['id' => 1, 'name' => 'Test', 'url' => 'https://example.com/hook', 'secret' => 'topsecret', 'events' => ['task.created'], 'is_active' => true]);
        $webhook->id = 1;

        (new SendWebhookNotification($webhook, 'task.created', ['task_id' => 1]))->handle();

        Http::assertSent(function ($request) {
            $body = $request->body();
            $expectedSignature = hash_hmac('sha256', $body, 'topsecret');

            return $request->url() === 'https://example.com/hook'
                && $request->hasHeader('X-Notula-Event', 'task.created')
                && $request->hasHeader('X-Notula-Signature', $expectedSignature);
        });
    }

    public function test_approving_task_dispatches_webhook_job(): void
    {
        Queue::fake();

        $unit = Unit::factory()->create();
        $admin = User::factory()->create(['unit_id' => $unit->id]);
        $admin->syncRoles(['admin']);
        Webhook::create(['unit_id' => $unit->id, 'name' => 'On Approve', 'url' => 'https://e.test', 'secret' => 's', 'events' => ['task.approved'], 'is_active' => true]);
        $task = Task::create(['unit_id' => $unit->id, 'title' => 'Task uji', 'status' => 'Review']);

        $this->actingAs($admin)->post(route('tasks.approve', $task))->assertRedirect();

        Queue::assertPushed(SendWebhookNotification::class, 1);
    }
}
