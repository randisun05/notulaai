<?php

namespace Tests\Feature\Meeting;

use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\DTO\AiTextResult;
use App\Services\Meeting\MeetingProcessingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HtmlSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private const XSS = '<script>alert(1)</script><img src=x onerror=alert(2)><p>Konten sah</p>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_meeting_agenda_is_sanitized_on_create(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $this->actingAs($user)->post(route('meetings.store'), [
            'title' => 'Rapat',
            'date' => now()->toDateString(),
            'agenda' => self::XSS,
            'attendees' => 'A, B',
        ])->assertRedirect();

        $agenda = Meeting::firstOrFail()->agenda;

        $this->assertStringContainsString('<p>Konten sah</p>', $agenda);
        $this->assertStringNotContainsString('<script', $agenda);
        $this->assertStringNotContainsString('onerror', $agenda);
        $this->assertStringNotContainsString('<img', $agenda);
    }

    public function test_meeting_can_still_be_created_without_an_agenda(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $this->actingAs($user)->post(route('meetings.store'), [
            'title' => 'Tanpa agenda',
            'date' => now()->toDateString(),
            'attendees' => 'A',
        ])->assertRedirect();

        $this->assertSame('', Meeting::firstOrFail()->agenda);
    }

    public function test_meeting_agenda_is_sanitized_on_update(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        $meeting = Meeting::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'title' => 'Rapat',
            'date' => now(),
            'agenda' => 'lama',
            'attendees' => 'A',
            'status' => 'Dijadwalkan',
        ]);

        $this->actingAs($user)->put(route('meetings.update', $meeting), [
            'title' => 'Rapat',
            'date' => now()->toDateString(),
            'agenda' => self::XSS,
            'attendees' => 'A',
        ])->assertRedirect();

        $this->assertStringNotContainsString('<script', $meeting->fresh()->agenda);
    }

    public function test_ai_summary_is_sanitized_before_it_is_stored(): void
    {
        Mail::fake();
        Storage::fake('public');

        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);

        Storage::disk('public')->put('sources/notes.txt', 'Andi: proyek selesai bulan depan.');

        $meeting = Meeting::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'title' => 'Rapat',
            'date' => now(),
            'agenda' => 'Agenda',
            'attendees' => 'A',
            'status' => 'Memproses',
            'source_file_path' => 'sources/notes.txt',
        ]);

        $provider = $this->createMock(TextGenerationProvider::class);
        $provider->method('generate')->willReturn(new AiTextResult(self::XSS, 'fake', 'fake-model'));

        $this->mock(AiManager::class, function ($mock) use ($provider) {
            $mock->shouldReceive('activeTextProvider')->andReturn('fake');
            $mock->shouldReceive('activeOcrProvider')->andReturn('fake');
            $mock->shouldReceive('activeTranscriptionProvider')->andReturn('fake');
            $mock->shouldReceive('text')->andReturn($provider);
        });

        $this->app->make(MeetingProcessingService::class)->process($meeting);

        $summary = $meeting->fresh()->summary;

        $this->assertStringContainsString('<p>Konten sah</p>', $summary);
        $this->assertStringNotContainsString('<script', $summary);
        $this->assertStringNotContainsString('onerror', $summary);
    }
}
