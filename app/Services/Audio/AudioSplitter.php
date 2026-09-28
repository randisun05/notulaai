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
     * Cari jeda hening pertama (≥ `ai.live.silence_seconds`) dalam rentang
     * [$from, $from + $length] detik. Mengembalikan titik tengah jeda (detik absolut)
     * — tempat memotong tanpa memenggal kalimat — atau null kalau tidak ada.
     */
    public function findSilence(string $sourcePath, float $from, float $length): ?float
    {
        $result = Process::timeout(120)->run([
            Config::get('ai.audio.ffmpeg_binary', 'ffmpeg'),
            '-hide_banner', '-nostdin',
            '-ss', $this->seconds($from), '-i', $sourcePath, '-t', $this->seconds($length),
            '-af', 'silencedetect=noise='.Config::get('ai.live.silence_noise', '-35dB').':d='.Config::get('ai.live.silence_seconds', 0.4),
            '-f', 'null', '-',
        ]);

        if ($result->failed()) {
            throw new RuntimeException('Gagal membaca rekaman untuk mencari jeda: '.mb_substr(trim($result->errorOutput()), -300));
        }

        // Waktu yang dilaporkan relatif terhadap -ss.
        preg_match_all('/silence_start: (-?[\d.]+)[\s\S]*?silence_end: ([\d.]+)/', $result->errorOutput(), $matches, PREG_SET_ORDER);

        foreach ($matches as [, $start, $end]) {
            return $from + (max(0.0, (float) $start) + (float) $end) / 2;
        }

        return null;
    }

    /**
     * Ambil rentang [$start, $start + $duration] (atau sampai akhir file bila null)
     * sebagai mp3 mono 16 kHz.
     */
    public function extract(string $sourcePath, float $start, ?float $duration, string $outputPath): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $result = Process::timeout(300)->run(array_merge(
            [Config::get('ai.audio.ffmpeg_binary', 'ffmpeg'), '-hide_banner', '-nostdin', '-y', '-ss', $this->seconds($start), '-i', $sourcePath],
            $duration !== null ? ['-t', $this->seconds($duration)] : [],
            ['-vn', '-ac', '1', '-ar', '16000', '-c:a', 'libmp3lame', '-b:a', '32k', $outputPath],
        ));

        if ($result->failed() || ! is_file($outputPath)) {
            throw new RuntimeException('Gagal memotong rekaman: '.mb_substr(trim($result->errorOutput()), -300));
        }
    }

    /**
     * Gabungkan beberapa rekaman (bagian rekaman live yang terputus) jadi satu mp3.
     *
     * @param  list<string>  $sourcePaths
     */
    public function concat(array $sourcePaths, string $outputPath): void
    {
        $inputs = [];
        foreach ($sourcePaths as $path) {
            array_push($inputs, '-i', $path);
        }
        $streams = implode('', array_map(fn (int $i) => "[{$i}:a]", array_keys($sourcePaths)));

        $result = Process::timeout((int) Config::get('ai.audio.split_timeout', 1800))->run(array_merge(
            [Config::get('ai.audio.ffmpeg_binary', 'ffmpeg'), '-hide_banner', '-nostdin', '-y'],
            $inputs,
            ['-filter_complex', $streams.'concat=n='.count($sourcePaths).':v=0:a=1[a]', '-map', '[a]',
                '-ac', '1', '-ar', '16000', '-c:a', 'libmp3lame', '-b:a', '32k', $outputPath],
        ));

        if ($result->failed()) {
            throw new RuntimeException('Gagal menggabungkan bagian rekaman: '.mb_substr(trim($result->errorOutput()), -300));
        }
    }

    private function seconds(float $value): string
    {
        return number_format(max(0.0, $value), 3, '.', '');
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
