<?php

namespace App\Services\Audio;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Memecah rekaman (audio atau video) menjadi potongan audio mono 16 kHz berdurasi
 * tetap lewat ffmpeg, supaya rekaman berjam-jam bisa ditranskrip per bagian.
 */
class AudioSplitter
{
    /**
     * @return list<array{path: string, start: float, end: float}> urut dari awal rekaman
     */
    public function split(string $sourcePath, string $outputDir): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException("File rekaman tidak ditemukan: {$sourcePath}");
        }

        File::ensureDirectoryExists($outputDir);
        $listFile = $outputDir.DIRECTORY_SEPARATOR.'segments.csv';

        $result = Process::timeout((int) Config::get('ai.audio.split_timeout', 1800))->run([
            Config::get('ai.audio.ffmpeg_binary', 'ffmpeg'),
            '-hide_banner', '-nostdin', '-y',
            '-i', $sourcePath,
            '-vn',                       // buang video (rekaman Zoom .mp4)
            '-ac', '1', '-ar', '16000',  // mono 16 kHz: cukup untuk suara, file kecil
            '-c:a', 'libmp3lame', '-b:a', '32k',
            '-f', 'segment',
            '-segment_time', (string) Config::get('ai.audio.segment_seconds', 600),
            '-reset_timestamps', '1',
            '-segment_list', $listFile,
            '-segment_list_type', 'csv',
            $outputDir.DIRECTORY_SEPARATOR.'part_%04d.mp3',
        ]);

        if ($result->failed()) {
            $error = trim($result->errorOutput()) ?: $result->output();
            throw new RuntimeException('Gagal memecah rekaman dengan ffmpeg: '.mb_substr($error, -500));
        }

        return $this->parseSegmentList($listFile, $outputDir);
    }

    /**
     * @return list<array{path: string, start: float, end: float}>
     */
    private function parseSegmentList(string $listFile, string $outputDir): array
    {
        if (! is_file($listFile)) {
            throw new RuntimeException('ffmpeg tidak menghasilkan daftar potongan audio.');
        }

        $minSeconds = (float) Config::get('ai.audio.min_segment_seconds', 1);
        $segments = [];

        foreach (file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$file, $start, $end] = str_getcsv($line);
            $path = $outputDir.DIRECTORY_SEPARATOR.$file;

            // ffmpeg sering menyisakan potongan terakhir sepersekian detik; buang saja.
            if ((float) $end - (float) $start < $minSeconds) {
                File::delete($path);

                continue;
            }

            $segments[] = ['path' => $path, 'start' => (float) $start, 'end' => (float) $end];
        }

        if ($segments === []) {
            throw new RuntimeException('Rekaman tidak berisi audio yang bisa diproses.');
        }

        return $segments;
    }
}
