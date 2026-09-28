<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Notulen Rapat — {{ $meeting->title }}</title>
    <style>
        @page { margin: 2cm 2.5cm 2cm 3cm; }
        body { font-family: "DejaVu Serif", "Times New Roman", serif; font-size: 11.5pt; line-height: 1.45; color: #000; }
        .kop { width: 100%; border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 18px; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 70px; }
        .kop .logo img { width: 64px; }
        .kop .name { text-align: center; font-size: 15pt; font-weight: bold; text-transform: uppercase; }
        .kop .address { text-align: center; font-size: 9.5pt; }
        h1 { text-align: center; font-size: 13pt; margin: 0; text-decoration: underline; letter-spacing: 1px; }
        .number { text-align: center; margin: 2px 0 18px; }
        table.identity { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.identity td { vertical-align: top; padding: 1px 0; }
        table.identity td.label { width: 32%; }
        table.identity td.colon { width: 3%; }
        h2 { font-size: 11.5pt; margin: 14px 0 4px; }
        ol, ul { margin: 2px 0 2px 0; padding-left: 20px; }
        li { margin-bottom: 3px; }
        p { margin: 2px 0 6px; text-align: justify; }
        table.followups { width: 100%; border-collapse: collapse; font-size: 10.5pt; }
        table.followups th, table.followups td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        table.followups th { background: #eee; }
        table.signatures { width: 100%; margin-top: 36px; page-break-inside: avoid; }
        table.signatures td { width: 50%; text-align: center; vertical-align: top; }
        .sign-space { height: 60px; }
        .sign-name { font-weight: bold; text-decoration: underline; }
        .approval { font-size: 8.5pt; color: #333; margin-top: 2px; }
        .watermark { position: fixed; top: 38%; left: 8%; font-size: 110pt; color: rgba(200, 0, 0, 0.12); transform: rotate(-35deg); font-weight: bold; z-index: -1; }
        .muted { color: #555; font-style: italic; }
    </style>
</head>
<body>
    @if ($isDraft)
        <div class="watermark">DRAF</div>
    @endif

    <table class="kop">
        <tr>
            @if ($logoPath)
                <td class="logo"><img src="{{ $logoPath }}" alt=""></td>
            @endif
            <td>
                <div class="name">{{ $setting->company_name }}</div>
                @if ($setting->company_address)
                    <div class="address">{{ $setting->company_address }}</div>
                @endif
            </td>
            @if ($logoPath)
                <td class="logo"></td>
            @endif
        </tr>
    </table>

    <h1>NOTULEN RAPAT</h1>
    <div class="number">Nomor: {{ $minutes->number ?: '.................................' }}</div>

    <table class="identity">
        <tr><td class="label">Hari/Tanggal</td><td class="colon">:</td><td>{{ $date->isoFormat('dddd, D MMMM Y') }}</td></tr>
        <tr><td class="label">Waktu</td><td class="colon">:</td><td>{{ $minutes->time_range ?: '-' }}</td></tr>
        <tr><td class="label">Tempat</td><td class="colon">:</td><td>{{ $minutes->location ?: '-' }}</td></tr>
        <tr><td class="label">Pimpinan Rapat</td><td class="colon">:</td><td>{{ $minutes->chairpersonDisplayName() ?: '-' }}{{ $minutes->chairperson_title ? ', '.$minutes->chairperson_title : '' }}</td></tr>
        <tr><td class="label">Notulis</td><td class="colon">:</td><td>{{ $minutes->minuteTaker?->name ?? '-' }}</td></tr>
        <tr><td class="label">Peserta</td><td class="colon">:</td><td>{!! nl2br(e($minutes->attendees ?: '-')) !!}</td></tr>
        <tr><td class="label">Acara</td><td class="colon">:</td><td>{!! nl2br(e($minutes->agenda ?: $meeting->title)) !!}</td></tr>
    </table>

    <h2>I. Pembukaan</h2>
    <p>{!! nl2br(e($minutes->opening ?: '-')) !!}</p>

    <h2>II. Pembahasan</h2>
    @if (count($minutes->discussion ?? []))
        <ol>
            @foreach ($minutes->discussion as $item)
                <li>
                    @if ($item['topic'])<strong>{{ $item['topic'] }}</strong><br>@endif
                    {!! nl2br(e($item['notes'])) !!}
                </li>
            @endforeach
        </ol>
    @else
        <p class="muted">Tidak ada.</p>
    @endif

    <h2>III. Keputusan/Kesimpulan</h2>
    @if (count($minutes->decisions ?? []))
        <ol>
            @foreach ($minutes->decisions as $decision)
                <li>{{ $decision }}</li>
            @endforeach
        </ol>
    @else
        <p class="muted">Tidak ada.</p>
    @endif

    <h2>IV. Tindak Lanjut</h2>
    @if ($actionItems->isNotEmpty())
        <table class="followups">
            <thead><tr><th style="width:6%">No</th><th>Uraian</th><th style="width:24%">Penanggung Jawab</th><th style="width:18%">Tenggat</th></tr></thead>
            <tbody>
                @foreach ($actionItems as $i => $item)
                    <tr>
                        <td style="text-align:center">{{ $i + 1 }}</td>
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->assignee_name ?: '-' }}</td>
                        <td>{{ $item->deadline ? $item->deadline->locale('id')->isoFormat('D MMMM Y') : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Tidak ada.</p>
    @endif

    <h2>V. Penutup</h2>
    <p>{!! nl2br(e($minutes->closing ?: '-')) !!}</p>

    <table class="signatures">
        <tr>
            <td>
                Mengesahkan,<br>Pimpinan Rapat
                <div class="sign-space"></div>
                <div class="sign-name">{{ $minutes->chairpersonDisplayName() ?: '.................................' }}</div>
                @if ($minutes->chairperson_title)<div>{{ $minutes->chairperson_title }}</div>@endif
                @if ($minutes->status === 'disahkan')
                    <div class="approval">Disahkan secara elektronik oleh {{ $minutes->approver?->name }}<br>pada {{ $minutes->approved_at->locale('id')->isoFormat('D MMMM Y, HH.mm') }} WIB</div>
                @endif
            </td>
            <td>
                <br>Notulis
                <div class="sign-space"></div>
                <div class="sign-name">{{ $minutes->minuteTaker?->name ?? '.................................' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
