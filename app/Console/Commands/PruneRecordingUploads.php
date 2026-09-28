<?php

namespace App\Console\Commands;

use App\Models\RecordingUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneRecordingUploads extends Command
{
    protected $signature = 'recordings:prune-uploads {--hours=48 : Hapus upload bertahap yang tidak bertambah selama sekian jam}';

    protected $description = 'Hapus upload rekaman bertahap yang ditinggalkan (file sementara bisa berukuran GB)';

    public function handle(): int
    {
        $stale = RecordingUpload::where('updated_at', '<=', now()->subHours((int) $this->option('hours')))->get();

        foreach ($stale as $upload) {
            Storage::disk('local')->delete($upload->partialPath());
            $upload->delete();
        }

        // File sementara yang barisnya sudah hilang (mis. rapatnya dihapus).
        $known = RecordingUpload::pluck('id')->map(fn ($id) => "recording_uploads/{$id}.part")->all();
        $orphans = array_diff(Storage::disk('local')->files('recording_uploads'), $known);
        Storage::disk('local')->delete($orphans);

        $this->info(count($stale).' upload kedaluwarsa dan '.count($orphans).' file yatim dihapus.');

        return self::SUCCESS;
    }
}
