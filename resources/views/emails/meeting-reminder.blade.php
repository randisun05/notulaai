<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengingat Rapat</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { width: 90%; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .header { font-size: 24px; font-weight: bold; }
        .content { margin-top: 20px; }
        .footer { margin-top: 30px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">Pengingat Rapat</div>
        <div class="content">
            <p>Halo, <strong>{{ $meeting->creator->name }}</strong>,</p>
            <p>Ini adalah pengingat bahwa Anda memiliki rapat yang dijadwalkan hari ini:</p>
            <ul>
                <li><strong>Judul:</strong> {{ $meeting->title }}</li>
                <li><strong>Tanggal:</strong> {{ \Carbon\Carbon::parse($meeting->date)->format('l, d F Y') }}</li>
                <li><strong>Waktu:</strong> {{ \Carbon\Carbon::parse($meeting->date)->format('H:i \W\I\B') }}</li>
            </ul>
            <p>Silakan persiapkan diri Anda. Terima kasih.</p>
        </div>
        <div class="footer">
            <p>Email ini dikirim secara otomatis oleh Sistem AI-Notula.</p>
        </div>
    </div>
</body>
</html>
