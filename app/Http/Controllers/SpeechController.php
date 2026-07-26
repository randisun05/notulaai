<?php

namespace App\Http\Controllers;

use App\Services\AI\AiManager;
use App\Services\AI\AiRequestLogger;
use App\Services\AI\Contracts\TranscriptionProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpeechController extends Controller
{
    public function __construct(
        private readonly TranscriptionProvider $transcription,
        private readonly AiRequestLogger $logger,
        private readonly AiManager $ai,
    ) {
    }

    public function transcribe(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:mp3,wav,m4a',
        ]);

        $path = $request->file('file')->store('audio', 'public');
        $fileName = basename($path);
        $provider = $this->ai->activeTranscriptionProvider();

        try {
            $result = $this->transcription->transcribe(
                absoluteFilePath: Storage::disk('public')->path($path),
                fileName: $fileName,
                language: 'Indonesian',
            );
        } catch (\RuntimeException $e) {
            $this->logger->logFailure('transcription', $provider, null, $fileName, $e->getMessage(), user: $request->user());

            return response()->json(['error' => 'Gagal mentranskripsi audio', 'details' => $e->getMessage()], 500);
        }

        $this->logger->logSuccess('transcription', $provider, null, $fileName, $result->text, durationMs: $result->durationMs, user: $request->user());

        return response()->json(['text' => $result->text, 'language' => $result->language]);
    }
}
