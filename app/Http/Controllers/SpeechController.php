<?php

namespace App\Http\Controllers;

use App\Services\AI\Contracts\TranscriptionProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpeechController extends Controller
{
    public function __construct(private readonly TranscriptionProvider $transcription)
    {
    }

    public function transcribe(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:mp3,wav,m4a',
        ]);

        $path = $request->file('file')->store('audio', 'public');

        try {
            $result = $this->transcription->transcribe(
                absoluteFilePath: Storage::disk('public')->path($path),
                fileName: basename($path),
                language: 'Indonesian',
            );
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'Gagal mentranskripsi audio', 'details' => $e->getMessage()], 500);
        }

        return response()->json(['text' => $result->text, 'language' => $result->language]);
    }
}
