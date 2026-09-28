<?php

namespace App\Services\Meeting;

use App\Models\MeetingMinutes;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\ListItem;

/**
 * Notula sebagai .docx (bisa disunting lagi di Word sebelum ditandatangani dan
 * diarsipkan). Susunannya sama dengan resources/views/exports/minutes-pdf.blade.php.
 */
class MinutesWordExporter
{
    private const BODY = ['alignment' => Jc::BOTH, 'lineHeight' => 1.5, 'spaceAfter' => 0];

    /**
     * @param  array<string, mixed>  $data  sama dengan data view PDF
     * @return string path file sementara (dihapus setelah diunduh)
     */
    public function export(array $data): string
    {
        ['meeting' => $meeting, 'minutes' => $minutes, 'actionItems' => $actionItems, 'setting' => $setting,
            'logoPath' => $logoPath, 'date' => $date, 'isDraft' => $isDraft, 'photos' => $photos, 'attendances' => $attendances] = $data;

        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(11);
        $section = $word->addSection(['marginTop' => 1134, 'marginBottom' => 1134, 'marginLeft' => 1418, 'marginRight' => 1247]);

        if ($isDraft) {
            $section->addHeader()->addText('DRAF — belum disetujui', ['color' => 'C00000', 'bold' => true, 'size' => 9], ['alignment' => Jc::END]);
        }

        // Kop: logo di tengah, nama instansi, alamat, garis bawah tebal.
        $center = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];
        if ($logoPath) {
            $section->addImage($logoPath, ['height' => 55, 'alignment' => Jc::CENTER]);
        }
        $kop = array_merge([mb_strtoupper((string) $setting->company_name)], array_filter(preg_split('/\R/', (string) $setting->company_address)));
        foreach ($kop as $i => $line) {
            $section->addText($line, ['bold' => $i === 0, 'size' => $i === 0 ? 12 : 11], $i === array_key_last($kop)
                ? $center + ['borderBottomSize' => 18, 'borderBottomColor' => '000000', 'spaceAfter' => 240]
                : $center);
        }

        $section->addText('NOTULA', ['bold' => true], $center);
        $section->addText(mb_strtoupper((string) ($minutes->title ?: $meeting->title)), ['bold' => true], $center);
        if ($minutes->number) {
            $section->addText('Nomor: '.$minutes->number, [], $center);
        }
        $section->addTextBreak();

        $rows = [
            'Hari/Tanggal' => $date->isoFormat('dddd').'/'.$date->isoFormat('D MMMM Y'),
            'Pukul' => $minutes->time_range ?: '-',
            'Tempat' => $minutes->location ?: '-',
            'Pemimpin Rapat' => $minutes->chairpersonLine() ?: '-',
            'Peserta Rapat' => $minutes->attendees ?: '-',
        ];
        if ($minutes->agenda) {
            $rows['Acara'] = $minutes->agenda;
        }
        $rows['Resume'] = '';
        $identity = $section->addTable();
        $cell = ['lineHeight' => 1.5, 'spaceAfter' => 0];
        foreach ($rows as $label => $value) {
            $identity->addRow();
            $identity->addCell(2300)->addText($label, [], $cell);
            $identity->addCell(300)->addText(':', [], $cell);
            $this->lines($identity->addCell(6200), (string) $value, $cell);
        }
        $section->addTextBreak();

        // Resume: poin berurutan — pembicara, isi, ➔ tanggapan.
        $indented = self::BODY + ['indentation' => ['left' => 360]];
        foreach ($minutes->resume ?? [] as $point) {
            [$firstLine, $rest] = $point['speaker'] ? [$point['speaker'], $point['text']] : $this->splitFirstLine($point['text']);
            $section->addListItem($firstLine, 0, [], ListItem::TYPE_BULLET_FILLED, self::BODY);
            $this->lines($section, $rest, $indented);
            if ($point['response']) {
                $this->lines($section, '➔  '.$point['response'], self::BODY + ['indentation' => ['left' => 720, 'hanging' => 360]]);
            }
        }

        $this->dashList($section, 'Kesimpulan rapat:', $minutes->decisions ?? []);
        $this->dashList($section, 'Tindak lanjut:', $actionItems->map(fn ($item) => $item->title
            .(($item->assignee_name || $item->deadline) ? ' ('.collect([
                $item->assignee_name ? 'PIC: '.$item->assignee_name : null,
                $item->deadline ? 'tenggat '.$item->deadline->locale('id')->isoFormat('D MMMM Y') : null,
            ])->filter()->implode('; ').')' : ''))->all());

        $section->addTextBreak();
        $section->addText($minutes->closing ?: MeetingMinutes::DEFAULT_CLOSING, [], self::BODY);
        $section->addTextBreak(2);

        // Tanda tangan notulen (kanan).
        $right = ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'indentation' => ['left' => 4800]];
        $section->addText('Notulen', [], $right + ['spaceAfter' => 1100]);
        $section->addText($minutes->minuteTaker->name ?? '.................................', ['underline' => Font::UNDERLINE_SINGLE], $right);
        if ($minutes->minuteTaker?->nip) {
            $section->addText('NIP. '.$minutes->minuteTaker->nip, [], $right);
        }

        if ($minutes->status === MeetingMinutes::STATUS_APPROVED) {
            $section->addTextBreak();
            $section->addText('Notula ini telah disetujui melalui aplikasi oleh '.$minutes->approver->name.' pada '
                .$minutes->approved_at->locale('id')->isoFormat('D MMMM Y, HH.mm').' WIB.', ['size' => 8, 'color' => '444444']);
        }

        if ($attendances->isNotEmpty()) {
            $section->addPageBreak();
            $section->addText('DAFTAR HADIR', ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $section->addText((string) ($minutes->title ?: $meeting->title), ['size' => 10], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $section->addText($date->isoFormat('dddd').'/'.$date->isoFormat('D MMMM Y'), ['size' => 10], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60]);
            $table->addRow();
            foreach (['No' => 600, 'Nama' => 2700, 'Jabatan' => 2500, 'Unit/Instansi' => 2300, 'Pukul' => 900] as $header => $width) {
                $table->addCell($width, ['bgColor' => 'EEEEEE'])->addText($header, ['bold' => true, 'size' => 10]);
            }
            foreach ($attendances as $i => $person) {
                $table->addRow();
                foreach ([(string) ($i + 1), $person->name, $person->position ?: '-', $person->organization ?: '-', $person->checked_in_at->format('H.i')] as $j => $value) {
                    $table->addCell([600, 2700, 2500, 2300, 900][$j])->addText($value, ['size' => 10]);
                }
            }
        }

        if ($photos->isNotEmpty()) {
            $section->addPageBreak();
            $section->addText('DOKUMENTASI', ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 240]);
            foreach ($photos as $photo) {
                [$width, $height] = getimagesize($photo) ?: [800, 600];
                $scale = min(430 / $width, 300 / $height, 1);
                $section->addImage($photo, ['width' => (int) ($width * $scale), 'height' => (int) ($height * $scale), 'alignment' => Jc::CENTER]);
                $section->addTextBreak();
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'notula').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($path);

        return $path;
    }

    /**
     * @param  list<string>  $items
     */
    private function dashList(Section $section, string $title, array $items): void
    {
        if ($items === []) {
            return;
        }

        $section->addListItem($title, 0, [], ListItem::TYPE_BULLET_FILLED, self::BODY);
        foreach ($items as $item) {
            $section->addText('-  '.$item, [], self::BODY + ['indentation' => ['left' => 720, 'hanging' => 240]]);
        }
    }

    /**
     * @return array{0: string, 1: string} baris pertama (jadi teks bullet) dan sisanya
     */
    private function splitFirstLine(string $text): array
    {
        $parts = preg_split('/\R/', $text, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * @param  array<string, mixed>  $paragraph
     */
    private function lines(AbstractContainer $container, string $text, array $paragraph): void
    {
        if ($text === '') {
            return;
        }
        foreach (preg_split('/\R/', $text) as $line) {
            $container->addText($line, [], $paragraph);
        }
    }
}
