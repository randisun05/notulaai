<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Task</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        p.subtitle { color: #6b7280; margin-top: 0; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background-color: #f3f4f6; }
        .overdue { color: #d03b3b; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Laporan Task</h1>
    <p class="subtitle">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Judul</th>
                <th>Unit</th>
                <th>PIC</th>
                <th>Prioritas</th>
                <th>Status</th>
                <th>Deadline</th>
                <th>Terlambat</th>
                <th>SLA (jam)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tasks as $task)
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>{{ $task->unit?->name ?? '-' }}</td>
                    <td>{{ $task->assignee?->name ?? $task->assignee_name ?? '-' }}</td>
                    <td>{{ $task->priority }}</td>
                    <td>{{ $task->status }}</td>
                    <td>{{ $task->deadline?->toDateString() ?? '-' }}</td>
                    <td class="{{ $task->is_overdue ? 'overdue' : '' }}">{{ $task->is_overdue ? 'Ya' : 'Tidak' }}</td>
                    <td>{{ $task->sla_hours ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">Tidak ada data task.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
