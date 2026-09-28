<?php

namespace App\Services\Meeting;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

/**
 * Notulen resmi sebagai .docx (bisa disunting lagi di Word sebelum diarsipkan).
 * Susunannya sama dengan resources/views/exports/minutes-pdf.blade.php.
 */
class MinutesWordExporter
{
    /**
     * @param  array<string, mixed>  $data  sama dengan data view PDF
     * @return string path file sementara (dihapus setelah diunduh)
     */
    public function export(array $data): string
    {
        ['meeting' => $meeting, 'minutes' => $minutes, 'actionItems' => $actionItems, 'setting' => $setting, 'logoPath' => $logoPath, 'date' => $date, 'isDraft' => $isDraft] = $data;

        $word = new PhpWord;
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);
        $section = $word->addSection(['marginTop' => 1134, 'marginBottom' => 1134, 'marginLeft' => 1701, 'marginRight' => 1418]);

        if ($isDraft) {
            $section->addHeader()->addText('DRAF — belum disahkan', ['color' => 'C00000', 'bold' => true, 'size' => 9], ['alignment' => Jc::END]);
        }

        // Kop
        $kop = $section->addTable(['borderBottomSize' => 18, 'borderBottomColor' => '000000', 'width' => 100 * 50, 'unit' => 'pct']);
        $kop->addRow();
        if ($logoPath) {
            $kop->addCell(1300)->addImage($logoPath, ['width' => 55, 'height' => 55]);
        }
        $cell = $kop->addCell(8000);
        $cell->addText(mb_strtoupper((string) $setting->company_name), ['bold' => true, 'size' => 15], ['alignment' => Jc::CENTER]);
        if ($setting->company_address) {
            $cell->addText($setting->company_address, ['size' => 10], ['alignment' => Jc::CENTER]);
        }
        $section->addTextBreak();

        $section->addText('NOTULEN RAPAT', ['bold' => true, 'size' => 13, 'underline' => Font::UNDERLINE_SINGLE], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $section->addText('Nomor: '.($minutes->number ?: '.................................'), [], ['alignment' => Jc::CENTER, 'spaceAfter' => 240]);

        $identity = $section->addTable();
        foreach ([
            'Hari/Tanggal' => $date->isoFormat('dddd, D MMMM Y'),
            'Waktu' => $minutes->time_range ?: '-',
            'Tempat' => $minutes->location ?: '-',
            'Pimpinan Rapat' => ($minutes->chairpersonDisplayName() ?: '-').($minutes->chairperson_title ? ', '.$minutes->chairperson_title : ''),
            'Notulis' => $minutes->minuteTaker->name ?? '-',
            'Peserta' => $minutes->attendees ?: '-',
            'Acara' => $minutes->agenda ?: $meeting->title,
        ] as $label => $value) {
            $identity->addRow();
            $identity->addCell(2700)->addText($label);
            $identity->addCell(300)->addText(':');
            $this->multiline($identity->addCell(6000), (string) $value);
        }

        $this->heading($section, 'I. Pembukaan');
        $this->multiline($section, $minutes->opening ?: '-');

        $this->heading($section, 'II. Pembahasan');
        foreach ($minutes->discussion ?: [['topic' => '', 'notes' => 'Tidak ada.']] as $i => $item) {
            if ($item['topic']) {
                $section->addText(($i + 1).'. '.$item['topic'], ['bold' => true], ['spaceAfter' => 0]);
            }
            $this->multiline($section, $item['notes'], ['indentation' => ['left' => 360]]);
        }

        $this->heading($section, 'III. Keputusan/Kesimpulan');
        foreach ($minutes->decisions ?: ['Tidak ada.'] as $i => $decision) {
            $section->addText(($minutes->decisions ? ($i + 1).'. ' : '').$decision, [], ['indentation' => ['left' => 360, 'hanging' => 360]]);
        }

        $this->heading($section, 'IV. Tindak Lanjut');
        if ($actionItems->isEmpty()) {
            $section->addText('Tidak ada.');
        } else {
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60]);
            $table->addRow();
            foreach (['No' => 600, 'Uraian' => 4600, 'Penanggung Jawab' => 2200, 'Tenggat' => 1600] as $header => $width) {
                $table->addCell($width, ['bgColor' => 'EEEEEE'])->addText($header, ['bold' => true, 'size' => 11]);
            }
            foreach ($actionItems as $i => $item) {
                $table->addRow();
                $table->addCell(600)->addText((string) ($i + 1), ['size' => 11]);
                $table->addCell(4600)->addText($item->title, ['size' => 11]);
                $table->addCell(2200)->addText($item->assignee_name ?: '-', ['size' => 11]);
                $table->addCell(1600)->addText($item->deadline ? $item->deadline->locale('id')->isoFormat('D MMMM Y') : '-', ['size' => 11]);
            }
        }

        $this->heading($section, 'V. Penutup');
        $this->multiline($section, $minutes->closing ?: '-');

        // Tanda tangan
        $section->addTextBreak();
        $signatures = $section->addTable();
        $signatures->addRow();
        $chair = $signatures->addCell(4500);
        $chair->addText('Mengesahkan,', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $chair->addText('Pimpinan Rapat', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 1100]);
        $chair->addText($minutes->chairpersonDisplayName() ?: '.................................', ['bold' => true, 'underline' => Font::UNDERLINE_SINGLE], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        if ($minutes->chairperson_title) {
            $chair->addText($minutes->chairperson_title, [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }
        if ($minutes->status === 'disahkan') {
            $chair->addText('Disahkan secara elektronik oleh '.$minutes->approver?->name.' pada '
                .$minutes->approved_at->locale('id')->isoFormat('D MMMM Y, HH.mm').' WIB', ['size' => 8, 'italic' => true], ['alignment' => Jc::CENTER]);
        }
        $taker = $signatures->addCell(4500);
        $taker->addText(' ', [], ['spaceAfter' => 0]);
        $taker->addText('Notulis', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 1100]);
        $taker->addText($minutes->minuteTaker->name ?? '.................................', ['bold' => true, 'underline' => Font::UNDERLINE_SINGLE], ['alignment' => Jc::CENTER]);

        $path = tempnam(sys_get_temp_dir(), 'notulen').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($path);

        return $path;
    }

    private function heading($section, string $text): void
    {
        $section->addText($text, ['bold' => true], ['spaceBefore' => 200, 'spaceAfter' => 60]);
    }

    /**
     * @param  AbstractContainer  $container
     * @param  array<string, mixed>  $paragraph
     */
    private function multiline($container, string $text, array $paragraph = []): void
    {
        foreach (preg_split('/\R/', $text) as $line) {
            $container->addText($line, [], $paragraph + ['alignment' => Jc::BOTH, 'spaceAfter' => 60]);
        }
    }
}
