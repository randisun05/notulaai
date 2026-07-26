<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eskalasi Task</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { width: 90%; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .header { font-size: 24px; font-weight: bold; color: #b91c1c; }
        .content { margin-top: 20px; }
        .footer { margin-top: 30px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">⚠ Eskalasi Task — {{ $setting->company_name ?? 'Notula AI' }}</div>
        <div class="content">
            <p>Halo, <strong>{{ $task->creator->name ?? 'Admin' }}</strong>,</p>
            <p>Task berikut dieskalasi karena <strong>{{ $reason }}</strong>:</p>
            <ul>
                <li><strong>Judul:</strong> {{ $task->title }}</li>
                <li><strong>PIC:</strong> {{ $task->assignee->name ?? $task->assignee_name ?? 'Belum ditentukan' }}</li>
                <li><strong>Deadline:</strong> {{ $task->deadline ? \Carbon\Carbon::parse($task->deadline)->format('l, d F Y') : 'Tidak ditentukan' }}</li>
                <li><strong>Status saat ini:</strong> {{ $task->status }}</li>
            </ul>
            <p>Mohon ditindaklanjuti. Terima kasih.</p>
        </div>
        <div class="footer">
            <p>Email ini dikirim secara otomatis oleh Sistem {{ $setting->company_name ?? 'Notula AI' }}.</p>
        </div>
    </div>
</body>
</html>
