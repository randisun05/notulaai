<?php

namespace Tests\Feature\Task;

use App\Exports\TasksExport;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class TaskExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_export_tasks(): void
    {
        $this->get(route('tasks.export.pdf'))->assertRedirect(route('login'));
        $this->get(route('tasks.export.excel'))->assertRedirect(route('login'));
    }

    public function test_pdf_export_returns_downloadable_pdf(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task PDF', 'status' => 'Todo', 'deadline' => now()->addDays(3)->toDateString()]);

        $response = $this->actingAs($user)->get(route('tasks.export.pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_excel_export_returns_downloadable_spreadsheet(): void
    {
        Excel::fake();

        $unit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task Excel', 'status' => 'Todo', 'deadline' => now()->addDays(3)->toDateString()]);

        $response = $this->actingAs($user)->get(route('tasks.export.excel'));

        $response->assertOk();
        Excel::assertDownloaded('tasks-'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_export_only_includes_tasks_from_users_unit(): void
    {
        Excel::fake();

        $unit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->syncRoles(['user']);

        Task::create(['unit_id' => $unit->id, 'title' => 'Task saya', 'status' => 'Todo']);
        Task::create(['unit_id' => $otherUnit->id, 'title' => 'Task unit lain', 'status' => 'Todo']);

        $this->actingAs($user)->get(route('tasks.export.excel'))->assertOk();

        Excel::assertDownloaded('tasks-'.now()->format('Y-m-d').'.xlsx', function (TasksExport $export) {
            $titles = $export->collection()->pluck('title');

            return $titles->contains('Task saya') && ! $titles->contains('Task unit lain');
        });
    }
}
