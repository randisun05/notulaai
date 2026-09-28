<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Notula — {{ $minutes->title ?: $meeting->title }}</title>
    {{-- Mengikuti format notula dinas: kop di tengah, NOTULA + judul kapital, identitas, Resume berupa
         poin (• pembicara, isi, ➔ tanggapan), penutup, tanda tangan Notulen + NIP, halaman DOKUMENTASI. --}}
    <style>
        @page { margin: 2cm 2.2cm 2cm 2.5cm; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10.5pt; line-height: 1.6; color: #000; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 16px; }
        .kop img { height: 70px; margin-bottom: 4px; }
        .kop .name { font-weight: bold; font-size: 11.5pt; text-transform: uppercase; }
        .kop .address { font-size: 10pt; line-height: 1.35; }
        .heading { text-align: center; font-weight: bold; margin-bottom: 16px; }
        .heading .title { text-transform: uppercase; }
        .heading .number { font-weight: normal; margin-top: 2px; }
        table.identity { border-collapse: collapse; margin: 0 0 10px 12px; }
        table.identity td { vertical-align: top; padding: 0 0 1px 0; }
        table.identity td.label { width: 130px; }
        table.identity td.colon { width: 12px; }
        ul.resume { margin: 0; padding-left: 16px; list-style-type: disc; }
        ul.resume > li { margin-bottom: 4px; text-align: justify; }
        .speaker { margin: 0; }
        .text { margin: 0; text-align: justify; }
        .response { margin: 2px 0 0 18px; text-indent: -18px; text-align: justify; }
        ul.dash { margin: 0; padding-left: 16px; list-style-type: none; }
        ul.dash li { text-align: left; }
        ul.dash li:before { content: "- "; margin-left: -12px; }
        .closing { margin: 18px 0 0 0; }
        .signature { width: 45%; margin: 30px 0 0 55%; text-align: center; page-break-inside: avoid; }
        .signature .space { height: 55px; }
        .signature .name { text-decoration: underline; }
        .approval { margin-top: 18px; font-size: 8pt; color: #444; }
        .watermark { position: fixed; top: 38%; left: 10%; font-size: 110pt; color: rgba(200, 0, 0, 0.12); transform: rotate(-35deg); font-weight: bold; z-index: -1; }
        .documentation { page-break-before: always; text-align: center; }
        .documentation h2 { font-size: 11pt; margin: 0 0 14px; }
        .documentation img { max-width: 100%; max-height: 9.5cm; margin-bottom: 14px; }
    </style>
</head>
<body>
    @if ($isDraft)
        <div class="watermark">DRAF</div>
    @endif

    <div class="kop">
        @if ($logoPath)
            <img src="{{ $logoPath }}" alt=""><br>
        @endif
        <div class="name">{{ $setting->company_name }}</div>
        @if ($setting->company_address)
            <div class="address">{!! nl2br(e($setting->company_address)) !!}</div>
        @endif
    </div>

    <div class="heading">
        <div>NOTULA</div>
        <div class="title">{{ $minutes->title ?: $meeting->title }}</div>
        @if ($minutes->number)
            <div class="number">Nomor: {{ $minutes->number }}</div>
        @endif
    </div>

    <table class="identity">
        <tr><td class="label">Hari/Tanggal</td><td class="colon">:</td><td>{{ $date->isoFormat('dddd') }}/{{ $date->isoFormat('D MMMM Y') }}</td></tr>
        <tr><td class="label">Pukul</td><td class="colon">:</td><td>{{ $minutes->time_range ?: '-' }}</td></tr>
        <tr><td class="label">Tempat</td><td class="colon">:</td><td>{{ $minutes->location ?: '-' }}</td></tr>
        <tr><td class="label">Pemimpin Rapat</td><td class="colon">:</td><td>{{ $minutes->chairpersonLine() ?: '-' }}</td></tr>
        <tr><td class="label">Peserta Rapat</td><td class="colon">:</td><td>{!! nl2br(e($minutes->attendees ?: '-')) !!}</td></tr>
        @if ($minutes->agenda)
            <tr><td class="label">Acara</td><td class="colon">:</td><td>{!! nl2br(e($minutes->agenda)) !!}</td></tr>
        @endif
        <tr><td class="label">Resume</td><td class="colon">:</td><td></td></tr>
    </table>

    <ul class="resume">
        @foreach ($minutes->resume ?? [] as $point)
            <li>
                @if ($point['speaker'])<p class="speaker">{{ $point['speaker'] }}</p>@endif
                <p class="text">{!! nl2br(e($point['text'])) !!}</p>
                @if ($point['response'])<p class="response">&#10132;&nbsp;&nbsp;{!! nl2br(e($point['response'])) !!}</p>@endif
            </li>
        @endforeach

        @if (count($minutes->decisions ?? []))
            <li>
                <p class="text">Kesimpulan rapat:</p>
                <ul class="dash">
                    @foreach ($minutes->decisions as $decision)
                        <li>{{ $decision }}</li>
                    @endforeach
                </ul>
            </li>
        @endif

        @if ($actionItems->isNotEmpty())
            <li>
                <p class="text">Tindak lanjut:</p>
                <ul class="dash">
                    @foreach ($actionItems as $item)
                        <li>{{ $item->title }}@if ($item->assignee_name || $item->deadline) ({{ collect([$item->assignee_name ? 'PIC: '.$item->assignee_name : null, $item->deadline ? 'tenggat '.$item->deadline->locale('id')->isoFormat('D MMMM Y') : null])->filter()->implode('; ') }})@endif</li>
                    @endforeach
                </ul>
            </li>
        @endif
    </ul>

    <p class="closing">{{ $minutes->closing ?: \App\Models\MeetingMinutes::DEFAULT_CLOSING }}</p>

    <div class="signature">
        Notulen
        <div class="space"></div>
        <div class="name">{{ $minutes->minuteTaker->name ?? '.................................' }}</div>
        @if ($minutes->minuteTaker?->nip)
            <div>NIP. {{ $minutes->minuteTaker->nip }}</div>
        @endif
    </div>

    @if ($minutes->status === 'disahkan')
        <div class="approval">Notula ini telah disetujui melalui aplikasi oleh {{ $minutes->approver?->name }}
            pada {{ $minutes->approved_at->locale('id')->isoFormat('D MMMM Y, HH.mm') }} WIB.</div>
    @endif

    @if ($photos->isNotEmpty())
        <div class="documentation">
            <h2>DOKUMENTASI</h2>
            @foreach ($photos as $photo)
                <img src="{{ $photo }}" alt="">
            @endforeach
        </div>
    @endif
</body>
</html>
