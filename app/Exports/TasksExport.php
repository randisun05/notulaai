<?php

namespace App\Exports;

use App\Models\Task;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TasksExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $tasks) {}

    public function collection(): Collection
    {
        return $this->tasks;
    }

    public function headings(): array
    {
        return ['Judul', 'Unit', 'PIC', 'Prioritas', 'Status', 'Deadline', 'Terlambat', 'SLA (jam)'];
    }

    public function map($task): array
    {
        /** @var Task $task */
        return [
            $task->title,
            $task->unit?->name,
            $task->assignee?->name ?? $task->assignee_name,
            $task->priority,
            $task->status,
            $task->deadline?->toDateString(),
            $task->is_overdue ? 'Ya' : 'Tidak',
            $task->sla_hours,
        ];
    }
}
