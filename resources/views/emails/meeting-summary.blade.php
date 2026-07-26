<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Notula Rapat</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }
        .container {
            width: 90%;
            max-width: 600px;
            margin: 20px auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .header {
            background-color: #4a90e2; /* Biru */
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .content p {
            margin-bottom: 20px;
        }
        .summary {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #eee;
            margin-bottom: 20px;
        }
        .summary h3 {
            margin-top: 0;
            color: #4a90e2;
        }
        .button {
            display: inline-block;
            background-color: #4a90e2;
            color: #ffffff;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .footer {
            background-color: #f4f4f4;
            color: #777;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Notula AI</h1>
        </div>
        <div class="content">
            {{--
                Kita gunakan pengecekan 'isset' untuk berjaga-jaga jika
                karena alasan apapun, variabel $user atau $meeting gagal di-load.
            --}}
            @if(isset($user) && isset($meeting))
                <p>Halo, <strong>{{ $user->name ?? 'Peserta Rapat' }}</strong>,</p>
                <p>Berikut adalah hasil rangkuman notula otomatis untuk rapat Anda:</p>

                <h2>{{ $meeting->title ?? 'Judul Rapat' }}</h2>
                <p><strong>Tanggal:</strong> {{ $meeting->date ? \Carbon\Carbon::parse($meeting->date)->isoFormat('dddd, D MMMM YYYY') : 'N/A' }}</p>

                <div class="summary">
                    <h3>Rangkuman Penting:</h3>
                    {{--
                        Kita menggunakan {!! !!} (raw HTML) karena rangkuman
                        dari AI sudah kita format dalam bentuk <ul> <li>
                    --}}
                    {!! $meeting->summary ?? '<p>Rangkuman tidak tersedia.</p>' !!}
                </div>

                <p>Anda dapat melihat detail lengkap, termasuk transkrip penuh, dengan mengklik tombol di bawah ini:</p>

                {{--
                    Kita gunakan url() sebagai ganti route() karena ini
                    dijalankan dari CLI (Job) dan mungkin tidak memiliki konteks 'request'
                --}}
                <a href="{{ $meeting->id ? url('/meetings/' . $meeting->id) : url('/') }}" class="button">Lihat Detail Rapat</a>

            @else
                <p>Rangkuman rapat sedang diproses atau telah selesai diproses.</p>
            @endif
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Notula AI. Semua hak cipta dilindungi.</p>
        </div>
    </div>
</body>
</html>

