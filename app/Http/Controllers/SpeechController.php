<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SpeechController extends Controller
{
    public function transcribe(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:mp3,wav,m4a',
        ]);

        $file = $request->file('file');
        $path = $file->store('audio', 'public');
        $fullPath = Storage::disk('public')->path($path);

        $response = Http::attach(
            'file', file_get_contents($fullPath), basename($path)
        )->post('http://127.0.0.1:5055/transcribe', [
            'language' => 'Indonesian',
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Gagal mentranskripsi audio', 'details' => $response->body()], 500);
        }

        return response()->json($response->json());
    }
}
