<?php

namespace Tests\Feature;

use App\Console\Commands\FailStuckMeetings;
use App\Jobs\EscalateOverdueTasks;
use App\Jobs\SendTaskDeadlineReminders;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use App\Services\Meeting\MeetingProcessingService;
use App\Services\Notification\InAppNotifier;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Mail::fake();

        $this->unit = Unit::factory()->create();
        $this->admin = User::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Admin Unit']);
        $this->admin->syncRoles(['admin']);
        $this->member = User::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Budi Santoso']);
        $this->member->syncRoles(['user']);
    }

    private function kinds(User $user): array
    {
        return $user->fresh()->notifications->pluck('data.kind')->all();
    }

    private function meeting(array $attributes = []): Meeting
    {
        return Meeting::create(array_merge([
            'user_id' => $this->member->id, 'unit_id' => $this->unit->id, 'title' => 'Rapat Q4',
            'date' => now(), 'agenda' => '-', 'attendees' => '-', 'status' => 'Dijadwalkan',
        ], $attributes));
    }

    private function task(array $attributes = []): Task
    {
        return Task::create(array_merge([
            'unit_id' => $this->unit->id, 'title' => 'Kirim laporan', 'status' => 'In Progress',
            'assignee_id' => $this->member->id, 'created_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_mention_creates_a_notification_linking_to_the_forum(): void
    {
        $meeting = $this->meeting();

        $this->actingAs($this->admin)->post(route('meetings.comments.store', $meeting), ['body' => 'Cek ini @Budi Santoso'])->assertRedirect();

        $notification = $this->member->fresh()->notifications->first();
        $this->assertSame('mention', $notification->data['kind']);
        $this->assertSame('Admin Unit', $notification->data['actor_name']);
        $this->assertStringEndsWith("/meetings/{$meeting->id}#forum", $notification->data['url']);
        $this->assertSame([], $this->kinds($this->admin), 'the author is never notified');
    }

    public function test_creating_or_reassigning_a_task_notifies_the_new_assignee_only(): void
    {
        $this->actingAs($this->admin)->post(route('tasks.store'), [
            'title' => 'Task manual', 'assignee_id' => $this->member->id, 'priority' => 'High',
        ]);
        $this->assertSame(['task_assigned'], $this->kinds($this->member));

        $task = Task::first();
        $other = User::factory()->create(['unit_id' => $this->unit->id]);

        // Same assignee again: no duplicate.
        $this->actingAs($this->admin)->put(route('tasks.update', $task), ['title' => 'Task manual', 'assignee_id' => $this->member->id, 'priority' => 'High']);
        $this->assertCount(1, $this->kinds($this->member));

        $this->actingAs($this->admin)->put(route('tasks.update', $task), ['title' => 'Task manual', 'assignee_id' => $other->id, 'priority' => 'High']);
        $this->assertSame(['task_assigned'], $this->kinds($other));
    }

    public function test_disposition_notifies_the_receiving_user(): void
    {
        $task = $this->task();
        $receiver = User::factory()->create(['unit_id' => $this->unit->id]);

        $this->actingAs($this->member)->post(route('tasks.dispositions.store', $task), ['to_user_id' => $receiver->id]);

        $this->assertSame(['task_assigned'], $this->kinds($receiver));
    }

    public function test_review_request_goes_to_unit_admins_and_the_decision_back_to_the_assignee(): void
    {
        $task = $this->task();
        $otherUnitAdmin = User::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        $otherUnitAdmin->syncRoles(['admin']);

        $this->actingAs($this->member)->post(route('tasks.submit-for-review', $task), ['note' => 'Sudah selesai.']);

        $this->assertSame(['task_review_requested'], $this->kinds($this->admin));
        $this->assertSame([], $this->kinds($otherUnitAdmin));

        $this->actingAs($this->admin)->post(route('tasks.reject', $task), ['reason' => 'Lampiran kurang']);
        $rejected = $this->member->fresh()->notifications->first();
        $this->assertSame('task_rejected', $rejected->data['kind']);
        $this->assertSame('Alasan: Lampiran kurang', $rejected->data['body']);

        $this->actingAs($this->member)->post(route('tasks.submit-for-review', $task), ['note' => 'Sudah dilengkapi.']);
        $this->actingAs($this->admin)->post(route('tasks.approve', $task));
        $this->assertContains('task_approved', $this->kinds($this->member));
    }

    public function test_review_request_falls_back_to_superadmins_when_the_unit_has_no_admin(): void
    {
        $this->admin->syncRoles(['user']);
        $superadmin = User::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        $superadmin->syncRoles(['superadmin']);

        $this->actingAs($this->member)->post(route('tasks.submit-for-review', $this->task()), ['note' => 'Selesai.']);

        $this->assertSame(['task_review_requested'], $this->kinds($superadmin));
    }

    public function test_scheduled_jobs_notify_like_their_emails(): void
    {
        $this->task(['title' => 'Besok', 'deadline' => now()->addDay()->toDateString(), 'status' => 'Todo']);
        app()->call([new SendTaskDeadlineReminders, 'handle']);
        $this->assertSame(['task_deadline'], $this->kinds($this->member));

        $this->task(['title' => 'Telat', 'deadline' => now()->subDays(2)->toDateString()]);
        app()->call([new EscalateOverdueTasks, 'handle']);
        $this->assertSame(['task_escalated'], $this->kinds($this->admin), 'escalation goes to the task creator');
    }

    public function test_finished_processing_notifies_the_meeting_creator(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sources/notes.txt', 'Andi: proyek selesai bulan depan.');
        $meeting = $this->meeting(['status' => 'Memproses', 'source_file_path' => 'sources/notes.txt']);

        $provider = $this->createMock(TextGenerationProvider::class);
        $provider->method('generate')->willReturn(new AiTextResult('<p>Ringkasan</p>', 'fake', 'fake-model'));
        $this->mock(AiManager::class, function ($mock) use ($provider) {
            $mock->shouldReceive('activeTextProvider')->andReturn('fake');
            $mock->shouldReceive('text')->andReturn($provider);
        });

        $this->app->make(MeetingProcessingService::class)->process($meeting);

        $this->assertSame(['meeting_processed'], $this->kinds($this->member));
        $this->assertSame([], $this->kinds($this->admin), 'unit members still get the e-mail, the bell is for the creator');
    }

    public function test_meeting_failure_notifies_its_creator(): void
    {
        $meeting = $this->meeting(['status' => 'Memproses']);
        $meeting->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        $this->artisan(FailStuckMeetings::class, ['--minutes' => 10])->assertSuccessful();

        $this->assertSame(['meeting_failed'], $this->kinds($this->member));
    }

    public function test_bell_endpoints_list_count_and_mark_read_only_own_notifications(): void
    {
        $notifier = app(InAppNotifier::class);
        $notifier->send($this->member, 'task_assigned', 'Satu', null, '/tasks/1');
        $notifier->send($this->member, 'task_assigned', 'Dua', null, '/tasks/2');
        $notifier->send($this->admin, 'task_assigned', 'Milik admin', null, '/tasks/3');

        $recent = $this->actingAs($this->member)->getJson(route('notifications.recent'))->assertOk()->json();
        $this->assertSame(2, $recent['unread_count']);
        $this->assertEqualsCanonicalizing(['Satu', 'Dua'], array_column($recent['items'], 'title'));

        $adminNotification = $this->admin->notifications()->first();
        $this->actingAs($this->member)->postJson(route('notifications.read', $adminNotification->id))->assertNotFound();

        $this->actingAs($this->member)->postJson(route('notifications.read', $recent['items'][0]['id']))
            ->assertOk()->assertJson(['unread_count' => 1]);

        $this->actingAs($this->member)->postJson(route('notifications.read-all'))->assertJson(['unread_count' => 0]);
        $this->assertSame(1, $this->admin->unreadNotifications()->count(), "another user's notifications are untouched");
    }

    public function test_unread_count_is_shared_with_every_page_and_the_list_page_filters(): void
    {
        app(InAppNotifier::class)->send($this->member, 'mention', 'Halo', null, '/meetings/1');
        app(InAppNotifier::class)->send($this->member, 'mention', 'Lama', null, '/meetings/1');
        $this->member->notifications()->where('data->title', 'Lama')->update(['read_at' => now()]);

        $this->actingAs($this->member)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.unread_notifications', 1));

        // The list page has its own "notifications" prop; the badge must survive it.
        $this->actingAs($this->member)->get(route('notifications.index', ['unread' => 1]))
            ->assertInertia(fn ($page) => $page->component('Notifications/Index')
                ->where('auth.unread_notifications', 1)
                ->has('notifications.data', 1)
                ->where('notifications.data.0.title', 'Halo'));
    }
}
